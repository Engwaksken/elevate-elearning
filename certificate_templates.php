<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
error_reporting(E_ALL);
ini_set('display_errors','0');
ini_set('log_errors','1');
ob_start();

require_once __DIR__ . '/scripts/connector.php';

$systemname  = $systemname ?? "Elevate Her 360";
$role        = $_SESSION[$systemname . "letype"] ?? '';
$user_unique = $_SESSION[$systemname . "leido"] ?? '';

date_default_timezone_set('Africa/Nairobi');

//function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function table_exists(mysqli $conn, string $table): bool {
    $table = $conn->real_escape_string($table);
    $res = $conn->query("SHOW TABLES LIKE '{$table}'");
    return $res && $res->num_rows > 0;
}
function column_exists(mysqli $conn, string $table, string $column): bool {
    $table=$conn->real_escape_string($table); $column=$conn->real_escape_string($column);
    $res=$conn->query("SHOW COLUMNS FROM `{$table}` LIKE '{$column}'");
    return $res && $res->num_rows > 0;
}
function json_response(array $data): void {
    while (ob_get_level() > 0) ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    exit;
}
function file_url(?string $path): string {
    $path=trim((string)$path);
    if ($path==='') return '';
    if (preg_match('/^https?:\/\//i',$path) || str_starts_with($path,'data:')) return $path;
    $path=ltrim($path,'/');
    if (file_exists(__DIR__.'/'.$path)) return $path;
    if (file_exists(__DIR__.'/uploads/templates/'.basename($path))) return 'uploads/templates/'.basename($path);
    if (file_exists(__DIR__.'/uploads/certificate_templates/'.basename($path))) return 'uploads/certificate_templates/'.basename($path);
    return $path;
}

if (isset($_GET['ajax'])) {
    error_reporting(0);
    while (ob_get_level() > 0) ob_end_clean();
    ob_start();
    try {
        $ajax = trim((string)$_GET['ajax']);

        if ($ajax === 'students') {
            $rows=[];
            if (!table_exists($conn,'users')) json_response(['status'=>'error','message'=>'users table not found.','data'=>[]]);
            $nameParts=[];
            foreach(['fname','firstname','first_name','name'] as $c){ if(column_exists($conn,'users',$c)){ $nameParts[]=$c; break; } }
            foreach(['other_name','middlename','middle_name'] as $c){ if(column_exists($conn,'users',$c)){ $nameParts[]=$c; break; } }
            foreach(['lname','lastname','last_name'] as $c){ if(column_exists($conn,'users',$c)){ $nameParts[]=$c; break; } }
            $nameSql = $nameParts ? "TRIM(CONCAT_WS(' ', ".implode(',',array_map(fn($c)=>"`$c`",$nameParts))."))" : (column_exists($conn,'users','email') ? "`email`" : "CONCAT('Student #', id)");
            $where="1=1";
            if (column_exists($conn,'users','acc_type')) $where.=" AND acc_type IN ('afgezt','student','participant','learner')";
            elseif (column_exists($conn,'users','role')) $where.=" AND role IN ('student','participant','learner','5')";
            elseif (column_exists($conn,'users','user_type')) $where.=" AND user_type IN ('student','participant','learner')";
            $studentNoSql=column_exists($conn,'users','student_no') ? "`student_no`" : "''";
            $emailSql=column_exists($conn,'users','email') ? "`email`" : "''";
            $res=$conn->query("SELECT id, {$nameSql} AS name, {$studentNoSql} AS student_no, {$emailSql} AS email FROM users WHERE {$where} ORDER BY name ASC LIMIT 1000");
            if(!$res) throw new Exception($conn->error);
            while($r=$res->fetch_assoc()){
                $name=trim((string)$r['name']) ?: ($r['email'] ?: ('Student #'.$r['id']));
                $certName=$name; // clean full name for the certificate (no student number)
                if(!empty($r['student_no'])) $name.=' - '.$r['student_no'];
                $rows[]=['id'=>(string)$r['id'],'name'=>$name,'cert_name'=>$certName];
            }
            json_response(['status'=>'success','data'=>$rows]);
        }

        if ($ajax === 'courses') {
            $rows=[]; $courseTable='';
            foreach(['courses','elearning_courses','course'] as $tbl){ if(table_exists($conn,$tbl)){ $courseTable=$tbl; break; } }
            if($courseTable==='') json_response(['status'=>'error','message'=>'courses table not found.','data'=>[]]);
            $nameCol='name';
            foreach(['name','course_name','title','course_title'] as $c){ if(column_exists($conn,$courseTable,$c)){ $nameCol=$c; break; } }
            $statusWhere=column_exists($conn,$courseTable,'status') ? "WHERE status IN ('active','Active','1','published','Published')" : "";
            $res=$conn->query("SELECT id, `{$nameCol}` AS name FROM `{$courseTable}` {$statusWhere} ORDER BY `{$nameCol}` ASC LIMIT 1000");
            if(!$res) throw new Exception($conn->error);
            while($r=$res->fetch_assoc()) $rows[]=['id'=>(string)$r['id'],'name'=>trim((string)$r['name']) ?: 'Course #'.$r['id']];
            json_response(['status'=>'success','data'=>$rows]);
        }

        if ($ajax === 'intakes') {
            $rows=[]; $intakeTable='';
            foreach(['intakes','cohorts','course_intakes','batches'] as $tbl){ if(table_exists($conn,$tbl)){ $intakeTable=$tbl; break; } }
            if($intakeTable==='') json_response(['status'=>'error','message'=>'intakes table not found.','data'=>[]]);
            $nameCol='name';
            foreach(['name','intake_name','cohort_name','title'] as $c){ if(column_exists($conn,$intakeTable,$c)){ $nameCol=$c; break; } }
            $launchCol=''; foreach(['date_launching','start_date','date_start','launch_date','started_at'] as $c){ if(column_exists($conn,$intakeTable,$c)){ $launchCol=$c; break; } }
            $endCol=''; foreach(['date_ending','end_date','date_end','ending_date','ended_at'] as $c){ if(column_exists($conn,$intakeTable,$c)){ $endCol=$c; break; } }
            $dateSql=($launchCol?", `{$launchCol}` AS date_launching":"").($endCol?", `{$endCol}` AS date_ending":"");
            $res=$conn->query("SELECT id, `{$nameCol}` AS name {$dateSql} FROM `{$intakeTable}` ORDER BY id DESC LIMIT 500");
            if(!$res) throw new Exception($conn->error);
            while($r=$res->fetch_assoc()){
                $rawFrom=!empty($r['date_launching'])?date('Y-m-d',strtotime($r['date_launching'])):'';
                $rawTo=!empty($r['date_ending'])?date('Y-m-d',strtotime($r['date_ending'])):'';
                $label=trim((string)$r['name']) ?: 'Intake #'.$r['id'];
                $rows[]=['id'=>(string)$r['id'],'name'=>$label,'date_from'=>$rawFrom,'date_to'=>$rawTo];
            }
            json_response(['status'=>'success','data'=>$rows]);
        }

        if ($ajax === 'certificate_no') {
            $year=preg_replace('/[^0-9]/','',$_GET['year'] ?? date('Y')) ?: date('Y');
            $prefix='EH360-CERT-'.$year.'-'; $next=1;
            if(table_exists($conn,'certificates') && column_exists($conn,'certificates','certificate_no')){
                $stmt=$conn->prepare("SELECT certificate_no FROM certificates WHERE certificate_no LIKE CONCAT(?, '%') ORDER BY id DESC LIMIT 1");
                if($stmt){ $stmt->bind_param('s',$prefix); $stmt->execute(); $res=$stmt->get_result();
                    if($res && $row=$res->fetch_assoc()){ $last=preg_replace('/[^0-9]/','',substr((string)$row['certificate_no'],strlen($prefix))); $next=((int)$last)+1; }
                    $stmt->close();
                }
            }
            json_response(['status'=>'success','certificate_no'=>$prefix.str_pad((string)$next,5,'0',STR_PAD_LEFT)]);
        }

        json_response(['status'=>'error','message'=>'Invalid AJAX request.','data'=>[]]);
    } catch(Throwable $e){ json_response(['status'=>'error','message'=>$e->getMessage(),'data'=>[]]); }
}

if (!in_array($role, ['sabrtw','xybrtw'], true)) { echo "<div class='alert alert-danger'>Access denied.</div>"; return; }

$templates = $conn->query("SELECT * FROM certificate_templates ORDER BY is_default DESC, id DESC");
?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

<link href="https://fonts.googleapis.com/css2?family=Garet:wght@300;400;500;700;900&display=swap" rel="stylesheet">
<style>
:root{--witu-orange:#E85D04;--witu-brown:#5C2D0A;--canvas-w:900px;--canvas-h:637px}
.ct-page{font-family:'Garet',sans-serif;background:#f4f0eb;min-height:100vh}.ct-heading{background:var(--witu-brown);color:#fff;padding:14px 24px;display:flex;align-items:center;justify-content:space-between}.ct-heading h2{margin:0;font-size:18px}.ct-heading-sub{font-size:12px;opacity:.8}
.ct-layout{display:flex;min-height:calc(100vh - 56px)}.ct-sidebar{width:375px;background:#fff;border-right:1px solid #e2d9d0;display:flex;flex-direction:column;overflow-y:auto}.ct-main{flex:1;background:#f4f0eb;overflow:auto;padding:28px}
.ct-tabs{display:flex;border-bottom:2px solid #e2d9d0}.ct-tab{padding:10px 15px;font-size:13px;font-weight:700;color:#9e8c80;cursor:pointer;border-bottom:2px solid transparent;margin-bottom:-2px}.ct-tab.active{color:var(--witu-orange);border-bottom-color:var(--witu-orange)}.ct-tab-content{display:none}.ct-tab-content.active{display:block}
.ct-section{border-bottom:1px solid #ece5df;padding:18px 20px}.ct-section-title{font-size:11px;font-weight:800;letter-spacing:.08em;text-transform:uppercase;color:#9e8c80;margin-bottom:12px}.ct-label{font-size:12px;font-weight:700;color:#4a3728;margin-bottom:4px;display:block}
.ct-input{width:100%;padding:8px 10px;border:1.5px solid #ddd4ca;border-radius:6px;font-size:13px;background:#fdfaf8;box-sizing:border-box}.ct-help{font-size:11px;color:#9e8c80;margin-top:4px}.ct-error{font-size:12px;color:#dc2626;margin-top:5px;display:none}
.ct-btn{display:inline-flex;align-items:center;gap:6px;padding:8px 14px;border-radius:6px;border:none;font-size:13px;font-weight:700;cursor:pointer;text-decoration:none}.ct-btn-primary{background:var(--witu-orange);color:#fff}.ct-btn-secondary{background:#f0e8e0;color:var(--witu-brown)}.ct-btn-danger{background:#fee2e2;color:#dc2626}.ct-btn-sm{padding:5px 10px;font-size:12px}.ct-btn-block{width:100%;justify-content:center;margin-top:8px}.ct-btn-icon{padding:5px 8px;border-radius:5px;border:1.5px solid #ddd4ca;background:#fdfaf8;cursor:pointer}.ct-btn-icon.active{background:var(--witu-orange);color:#fff;border-color:var(--witu-orange)}
.upload-zone{border:2px dashed #d5c8bf;border-radius:10px;padding:18px;text-align:center;cursor:pointer;background:#faf6f3}.upload-zone:hover,.upload-zone.drag-over{border-color:var(--witu-orange);background:#fff5ee}.upload-zone input[type=file]{display:none}.upload-zone-icon{font-size:26px;margin-bottom:6px}.upload-zone-text{font-size:12px;color:#9e8c80;word-break:break-word}
.logo-strip{display:flex;flex-wrap:wrap;gap:6px;margin-top:8px}.logo-thumb{position:relative;width:54px;height:36px;border-radius:4px;border:1.5px solid #e2d9d0;cursor:grab;background:#faf6f3}.logo-thumb img{width:100%;height:100%;object-fit:contain}.logo-thumb-del{position:absolute;top:-6px;right:-6px;width:16px;height:16px;background:#ef4444;color:#fff;border-radius:50%;border:none;font-size:9px}
.field-tags{display:flex;flex-wrap:wrap;gap:6px}.field-tag{padding:5px 10px;background:#fff5ee;border:1.5px solid #f5c5a3;border-radius:20px;font-size:11px;font-weight:800;color:var(--witu-orange);cursor:pointer}
.rte-toolbar{display:flex;gap:4px;flex-wrap:wrap;padding:6px 8px;background:#f7f3ef;border:1.5px solid #ddd4ca;border-bottom:none;border-radius:6px 6px 0 0}.rte-toolbar-sep{width:1px;height:20px;background:#ddd4ca;margin:0 2px}.rte-editor{min-height:75px;max-height:150px;overflow:auto;padding:8px 10px;border:1.5px solid #ddd4ca;border-radius:0 0 6px 6px;font-size:13px;background:#fdfaf8;outline:none;line-height:1.6}
.canvas-wrapper{background:#e8ddd4;border-radius:14px;padding:24px;display:flex;flex-direction:column;align-items:flex-start;gap:16px}.canvas-toolbar{display:flex;align-items:center;gap:10px;flex-wrap:wrap}.canvas-label{font-size:12px;font-weight:800;color:#7a6355;text-transform:uppercase}
#cert-canvas{width:var(--canvas-w);height:var(--canvas-h);position:relative;background:#fff;border-radius:8px;box-shadow:0 8px 40px rgba(0,0,0,.18);overflow:hidden;user-select:none}#cert-canvas.accepting{outline:3px dashed var(--witu-orange)}#cert-bg{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;pointer-events:none;display:none}
.ce-elem{position:absolute;cursor:move;border:1.5px dashed transparent;border-radius:4px;padding:3px 5px;min-width:40px;min-height:20px;box-sizing:border-box}.ce-elem:hover,.ce-elem.selected{border-color:var(--witu-orange)!important}.ce-resize{position:absolute;bottom:-5px;right:-5px;width:12px;height:12px;background:var(--witu-orange);border-radius:2px;cursor:se-resize;display:none}.ce-elem.selected .ce-resize{display:block}.ce-elem-del{position:absolute;top:-9px;right:-9px;width:18px;height:18px;background:#ef4444;color:#fff;border-radius:50%;border:none;font-size:10px;display:none;align-items:center;justify-content:center;z-index:100}.ce-elem.selected .ce-elem-del{display:flex}.ce-text{overflow:visible;line-height:1.4;pointer-events:none;white-space:pre-wrap;word-break:break-word}.ce-logo{display:block;width:100%;height:100%;object-fit:contain;pointer-events:none}
.prop-panel{background:#fff;border:1px solid #e2d9d0;border-radius:10px;padding:16px}.prop-row,.prop-toolbar{display:flex;align-items:center;gap:8px;margin-bottom:10px;flex-wrap:wrap}.prop-row label,.prop-toolbar label{font-size:11px;font-weight:700;color:#7a6355;min-width:60px}.prop-row input,.prop-row select{flex:1;padding:5px 8px;border:1.5px solid #ddd4ca;border-radius:5px;font-size:12px;min-width:50px}
.tpl-item{display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:8px;border:1.5px solid #e8ddd4;background:#fdfaf8;margin-bottom:8px}.tpl-item-thumb{width:60px;height:42px;border-radius:5px;object-fit:cover;border:1px solid #ddd;background:#f0e8e0;flex-shrink:0}.tpl-item-info{flex:1;min-width:0}.tpl-item-name{font-size:13px;font-weight:800;color:#3d2b1f;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.tpl-item-meta{font-size:11px;color:#9e8c80;margin-top:2px}.tpl-badge-default{background:#fff5ee;color:var(--witu-orange);border:1px solid #f5c5a3;border-radius:10px;font-size:10px;font-weight:800;padding:2px 7px}.tpl-item-actions{display:flex;gap:5px}
.ct-modal-backdrop{position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:1000;display:none;align-items:center;justify-content:center}.ct-modal-backdrop.open{display:flex}.ct-modal{background:#fff;border-radius:14px;padding:28px;width:540px;max-width:95vw;box-shadow:0 20px 60px rgba(0,0,0,.3);position:relative}.ct-modal-title{font-size:18px;font-weight:800;color:var(--witu-brown);margin-bottom:20px}.ct-modal-close{position:absolute;top:16px;right:16px;background:none;border:none;font-size:20px;color:#9e8c80;cursor:pointer}.ct-alert{padding:10px 14px;border-radius:7px;font-size:13px;margin:12px 24px}.ct-alert-success{background:#dcfce7;color:#16a34a;border:1px solid #86efac}.ct-alert-danger{background:#fee2e2;color:#dc2626;border:1px solid #fca5a5}
@media(max-width:850px){.ct-layout{flex-direction:column}.ct-sidebar{width:100%}}
</style>

<div class="ct-page">
  <div class="ct-heading">
    <div><h2><i class="fa fa-certificate"></i> Certificate Template Designer</h2><div class="ct-heading-sub">Edit saved templates, save text properties, and generate certificates</div></div>
    <button class="ct-btn ct-btn-primary" onclick="openSaveModal()"><i class="fa fa-save"></i> <span id="top-save-label">Save Template</span></button>
  </div>

  <?php if (isset($_SESSION[$systemname."successmsg"])): ?><div class="ct-alert ct-alert-success"><?= h($_SESSION[$systemname."successmsg"]); ?></div><?php unset($_SESSION[$systemname."successmsg"]); endif; ?>
  <?php if (isset($_SESSION[$systemname."errormsg"])): ?><div class="ct-alert ct-alert-danger"><?= h($_SESSION[$systemname."errormsg"]); ?></div><?php unset($_SESSION[$systemname."errormsg"]); endif; ?>

  <div class="ct-layout">
    <div class="ct-sidebar">
      <div class="ct-tabs">
        <div class="ct-tab active" data-tab="design">Design</div>
        <div class="ct-tab" data-tab="templates">Templates</div>
        <div class="ct-tab" data-tab="generate">Generate</div>
      </div>

      <div class="ct-tab-content active" id="tab-design">
        <div class="ct-section">
          <div class="ct-section-title">Current Template</div>
          <input type="hidden" id="editing-template-id" value="">
          <div class="ct-help" id="editing-status">Creating new template.</div>
          <button class="ct-btn ct-btn-secondary ct-btn-sm ct-btn-block" onclick="newTemplate()"><i class="fa fa-plus"></i> New Template</button>
        </div>

        <div class="ct-section">
          <div class="ct-section-title">Certificate Background</div>
          <div class="upload-zone" id="bg-upload-zone" onclick="document.getElementById('bg-file').click()">
            <div class="upload-zone-icon"><i class="fa fa-file-upload"></i></div>
            <div class="upload-zone-text">Click or drag JPG, PNG, WEBP, or PDF</div>
            <input type="file" id="bg-file" accept="image/png,image/jpeg,image/webp,application/pdf,.pdf" onchange="loadBackground(this)">
          </div>
          <button class="ct-btn ct-btn-secondary ct-btn-sm ct-btn-block" onclick="clearBackground()"><i class="fa fa-times"></i> Clear Background</button>
        </div>

        <div class="ct-section">
          <div class="ct-section-title">Logos / Signature Images</div>
          <div class="upload-zone" onclick="document.getElementById('logo-file').click()">
            <div class="upload-zone-icon"><i class="fa fa-image"></i></div>
            <div class="upload-zone-text">Add logos or signature image</div>
            <input type="file" id="logo-file" accept="image/*" multiple onchange="addLogos(this)">
          </div>
          <div class="logo-strip" id="logo-strip"></div>
          <p class="ct-help">Drag image onto the canvas.</p>
        </div>

        <div class="ct-section">
          <div class="ct-section-title">Dynamic Fields</div>
          <div class="field-tags">
            <?php foreach(['{student_name}','{course_name}','{course_date_from}','{course_date_to}','{year}','{certificate_no}','{intake_name}','{signature}'] as $tag): ?>
              <div class="field-tag" data-field="<?= h($tag) ?>" onclick="addTextField(this)"><?= h($tag) ?></div>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="ct-section">
          <div class="ct-section-title">Custom Text</div>
          <div class="rte-toolbar">
            <button type="button" class="ct-btn-icon" id="rte-bold-btn" onclick="rteExec('bold')"><strong>B</strong></button>
            <button type="button" class="ct-btn-icon" id="rte-italic-btn" onclick="rteExec('italic')"><em>I</em></button>
            <button type="button" class="ct-btn-icon" id="rte-underline-btn" onclick="rteExec('underline')"><u>U</u></button>
            <div class="rte-toolbar-sep"></div>
            <button type="button" class="ct-btn-icon" onclick="rteInsertBreak()">BR</button>
            <button type="button" class="ct-btn-icon" onclick="rteTransform('upper')">AA</button>
            <button type="button" class="ct-btn-icon" onclick="rteTransform('lower')">aa</button>
            <button type="button" class="ct-btn-icon" onclick="rteTransform('sentence')">Aa</button>
            <button type="button" class="ct-btn-icon" onclick="rteTransform('title')">Ab</button>
          </div>
          <div id="rte-editor" class="rte-editor" contenteditable="true" onkeydown="rteKeydown(event)" oninput="updateRteToolbarState()"></div>
          <button class="ct-btn ct-btn-secondary ct-btn-sm ct-btn-block" onclick="addRichText()"><i class="fa fa-plus"></i> Add to Canvas</button>
        </div>

        <div class="ct-section" id="prop-panel-section" style="display:none;">
          <div class="ct-section-title">Selected Element</div>
          <div class="prop-panel">
            <div class="prop-toolbar" id="pp-case-row"><label>Case</label>
              <button type="button" class="ct-btn-icon" onclick="applyCase('upper')">AA</button>
              <button type="button" class="ct-btn-icon" onclick="applyCase('lower')">aa</button>
              <button type="button" class="ct-btn-icon" onclick="applyCase('sentence')">Aa</button>
              <button type="button" class="ct-btn-icon" onclick="applyCase('title')">Ab</button>
            </div>
            <div class="prop-row" id="pp-bold-row"><label>Style</label>
              <button type="button" class="ct-btn-icon" id="pp-bold-toggle" onclick="togglePropBold()"><strong>B</strong></button>
              <button type="button" class="ct-btn-icon" id="pp-italic-toggle" onclick="togglePropStyle('fontStyle','italic','normal')"><em>I</em></button>
              <button type="button" class="ct-btn-icon" id="pp-underline-toggle" onclick="togglePropStyle('textDecoration','underline','none')"><u>U</u></button>
            </div>
            <div class="prop-row"><label>Font size</label><input type="number" id="pp-fontsize" min="6" max="120" onchange="applyProp('fontSize',this.value+'px')"></div>
            <div class="prop-row"><label>Color</label><input type="color" id="pp-color" onchange="applyProp('color',this.value);setValue('pp-color-hex',this.value)"><input type="text" id="pp-color-hex" style="max-width:90px" onchange="setValue('pp-color',this.value);applyProp('color',this.value)"></div>
            <div class="prop-row"><label>Weight</label><select id="pp-weight" onchange="applyProp('fontWeight',this.value)"><option value="300">Light</option><option value="400">Normal</option><option value="700">Bold</option><option value="900">Black</option></select></div>
            <div class="prop-row"><label>Font</label><select id="pp-family" onchange="applyProp('fontFamily', this.value)">
    <option value="'Segoe UI',sans-serif">Segoe UI</option>
    <option value="Calibri,sans-serif">Calibri</option>
    <option value="'Garet',sans-serif">Garet</option>
    <option value="Georgia,serif">Georgia</option>
    <option value="'Times New Roman',serif">Times New Roman</option>
    <option value="Arial,sans-serif">Arial</option>
    <option value="'Palatino Linotype',serif">Palatino</option>
    <option value="'Trebuchet MS',sans-serif">Trebuchet MS</option>
</select></div>
            <div class="prop-row"><label>Align</label><select id="pp-align" onchange="applyProp('textAlign',this.value)"><option value="left">Left</option><option value="center">Center</option><option value="right">Right</option></select></div>
            <div class="prop-row"><label>Line H.</label><input type="number" id="pp-lineheight" min="1" max="4" step="0.1" onchange="applyProp('lineHeight',this.value)"></div>
            <div class="prop-row"><label>Width</label><input type="number" id="pp-width" min="20" max="900" onchange="applyElemSize('width',this.value)"></div>
            <div class="prop-row"><label>Height</label><input type="number" id="pp-height" min="10" max="637" onchange="applyElemSize('height',this.value)"></div>
            <button class="ct-btn ct-btn-danger ct-btn-sm ct-btn-block" onclick="deleteSelected()"><i class="fa fa-trash"></i> Remove Element</button>
          </div>
        </div>

        <div class="ct-section"><button class="ct-btn ct-btn-secondary ct-btn-sm ct-btn-block" onclick="clearCanvas()"><i class="fa fa-trash"></i> Clear Elements</button></div>
      </div>

      <div class="ct-tab-content" id="tab-templates">
        <div class="ct-section">
          <div class="ct-section-title">Saved Templates</div>
          <?php if ($templates && $templates->num_rows > 0): $templates->data_seek(0); while($t=$templates->fetch_assoc()):
            $thumb=file_url($t['background_path'] ?? ''); $isPdf=strtolower(pathinfo($thumb,PATHINFO_EXTENSION))==='pdf'; ?>
            <div class="tpl-item">
              <?php if($thumb && !$isPdf): ?><img src="<?= h($thumb) ?>" class="tpl-item-thumb" alt=""><?php else: ?><div class="tpl-item-thumb" style="display:flex;align-items:center;justify-content:center;"><i class="fa <?= $isPdf?'fa-file-pdf':'fa-file' ?>"></i></div><?php endif; ?>
              <div class="tpl-item-info">
                <div class="tpl-item-name"><?= h($t['template_name']) ?></div>
                <div class="tpl-item-meta"><?= (int)$t['is_default'] ? '<span class="tpl-badge-default">Default</span>' : '' ?> <?= h($t['status'] ?? 'active') ?></div>
              </div>
              <div class="tpl-item-actions">
                <button class="ct-btn ct-btn-secondary ct-btn-sm" onclick='loadTemplate(<?= (int)$t['id'] ?>, <?= json_encode($t, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_SLASHES) ?>)'><i class="fa fa-edit"></i></button>
                <a href="scripts/preview_certificate_template.php?id=<?= (int)$t['id'] ?>" target="_blank" class="ct-btn ct-btn-secondary ct-btn-sm"><i class="fa fa-eye"></i></a>
                <button class="ct-btn ct-btn-danger ct-btn-sm" onclick="confirmDelete(<?= (int)$t['id'] ?>)"><i class="fa fa-trash"></i></button>
              </div>
            </div>
          <?php endwhile; else: ?><p style="font-size:13px;color:#9e8c80;text-align:center;padding:20px 0;">No templates yet.</p><?php endif; ?>
        </div>
      </div>

      <div class="ct-tab-content" id="tab-generate">
        <div class="ct-section">
          <div class="ct-section-title">Generate Certificate</div>
          <form method="post" action="scripts/generate_certificate.php" target="_blank" onsubmit="prepareGenerate()">
            <input type="hidden" name="layout_json" id="gen-layout-json"><input type="hidden" name="bg_data_url" id="gen-bg-data"><input type="hidden" name="canvas_width" id="gen-canvas-w"><input type="hidden" name="canvas_height" id="gen-canvas-h">
            <label class="ct-label">Template</label><select name="template_id" class="ct-input" id="gen-template-id"><option value="">Current canvas</option><?php if($templates && $templates->num_rows>0): $templates->data_seek(0); while($t=$templates->fetch_assoc()): ?><option value="<?= (int)$t['id'] ?>"><?= h($t['template_name']) ?></option><?php endwhile; endif; ?></select><br>
            <label class="ct-label">Student Name *</label><select id="student_select" class="ct-input" onchange="copySelectedText(this,'student_name')" required><option value="">Loading students...</option></select><input type="hidden" name="student_name" id="student_name"><div class="ct-error" id="students_error"></div><br>
            <label class="ct-label">Course Name *</label><select id="course_select" class="ct-input" onchange="copySelectedText(this,'course_name')" required><option value="">Loading courses...</option></select><input type="hidden" name="course_name" id="course_name"><div class="ct-error" id="courses_error"></div><br>
            <label class="ct-label">Intake Name</label><select id="intake_select" class="ct-input" onchange="copySelectedText(this,'intake_name');fillIntakeDates(this)"><option value="">Loading intakes...</option></select><input type="hidden" name="intake_name" id="intake_name"><div class="ct-error" id="intakes_error"></div><br>
            <label class="ct-label">Date From</label><input type="date" name="course_date_from" id="course_date_from" class="ct-input"><br>
            <label class="ct-label">Date To</label><input type="date" name="course_date_to" id="course_date_to" class="ct-input"><br>
            <label class="ct-label">Year</label><input type="number" name="year" id="cert_year" class="ct-input" value="<?= date('Y') ?>" onchange="generateCertificateNo()"><br>
            <label class="ct-label">Certificate No.</label><input type="text" name="certificate_no" id="certificate_no" class="ct-input" readonly><br>
            <label class="ct-label">Signature</label><input type="text" name="signature" class="ct-input" placeholder="e.g. Executive Director">
            <button type="submit" class="ct-btn ct-btn-primary ct-btn-block"><i class="fa fa-download"></i> Generate PDF</button>
          </form>
        </div>
      </div>
    </div>

    <div class="ct-main"><div class="canvas-wrapper"><div class="canvas-toolbar"><span class="canvas-label">Canvas</span><span id="canvas-size-display" style="font-size:11px;color:#9e8c80;"></span><button class="ct-btn ct-btn-secondary ct-btn-sm" onclick="fitCanvas()">Fit</button><button class="ct-btn ct-btn-secondary ct-btn-sm" onclick="zoomIn()">+</button><button class="ct-btn ct-btn-secondary ct-btn-sm" onclick="zoomOut()">-</button><span id="zoom-display" style="font-size:12px;color:#9e8c80;min-width:40px;text-align:center;"></span></div><div id="canvas-scale-wrapper" style="transform-origin:top left;"><div id="cert-canvas"><img id="cert-bg" src="" alt="certificate background"></div></div></div></div>
  </div>
</div>

<div class="ct-modal-backdrop" id="saveModal"><div class="ct-modal">
  <button class="ct-modal-close" onclick="closeModal('saveModal')">x</button>
  <div class="ct-modal-title" id="save-modal-title">Save Template</div>
  <form method="post" action="scripts/process_certificate_template.php" enctype="multipart/form-data" onsubmit="injectLayoutData()">
    <input type="hidden" name="template_id" id="save-template-id">
    <input type="hidden" name="layout_json" id="save-layout-json">
    <input type="hidden" name="logos_json" id="save-logos-json">
    <input type="hidden" name="bg_data_url" id="save-bg-data-url">
    <input type="hidden" name="existing_background_path" id="existing-background-path">
    <input type="hidden" name="existing_logo_path" id="existing-logo-path">
    <input type="hidden" name="existing_signature_path" id="existing-signature-path">
    <label class="ct-label">Template Name *</label><input type="text" name="template_name" id="save-template-name" class="ct-input" required><br><br>
    <label class="ct-label">Upload Designed Certificate</label><input type="file" name="designed_certificate" class="ct-input" accept="image/png,image/jpeg,image/webp,application/pdf,.pdf">
    <div class="ct-help">Leave empty while editing to keep the existing background.</div><br>
    <label class="ct-label">Certificate Title</label><input type="text" name="title" id="save-title" class="ct-input" value="Certificate of Completion"><br><br>
    <label class="ct-label">Body Text</label><textarea name="body_text" id="save-body-text" class="ct-input" rows="3"></textarea><br><br>
    <label class="ct-label">Footer Text</label><input type="text" name="footer_text" id="save-footer-text" class="ct-input" value="Issued by Women in Technology Uganda (WITU)"><br><br>
    <label class="ct-label">Template Status</label><select name="status" id="save-status" class="ct-input"><option value="active">Active</option><option value="inactive">Inactive</option></select><br><br>
    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px;color:#4a3728;"><input type="checkbox" name="is_default" id="save-is-default" value="1"> Mark as default template</label>
    <button type="submit" name="save_template" class="ct-btn ct-btn-primary ct-btn-block"><i class="fa fa-save"></i> <span id="save-submit-label">Save Template</span></button>
  </form>
</div></div>

<div class="ct-modal-backdrop" id="deleteModal"><div class="ct-modal" style="max-width:380px;">
  <button class="ct-modal-close" onclick="closeModal('deleteModal')">x</button><div class="ct-modal-title" style="color:#dc2626;">Delete Template</div><p style="font-size:14px;color:#555;margin:0 0 20px;">Are you sure you want to delete this template?</p>
  <form method="post" action="scripts/process_certificate_template.php"><input type="hidden" name="delete_id" id="delete-id-field"><button type="button" class="ct-btn ct-btn-secondary" onclick="closeModal('deleteModal')">Cancel</button> <button type="submit" name="delete_template" class="ct-btn ct-btn-danger">Delete</button></form>
</div></div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
<script>
if(typeof pdfjsLib!=='undefined'){pdfjsLib.GlobalWorkerOptions.workerSrc="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js";}
const canvas=document.getElementById('cert-canvas'), canvasBg=document.getElementById('cert-bg');
let elements=[], selectedId=null, logoStore=[], zoom=1.0, bgDataUrl='', currentBgPath='', elemIdCounter=0;
const CANVAS_W=900, CANVAS_H=637;
canvas.style.width=CANVAS_W+'px'; canvas.style.height=CANVAS_H+'px';
document.getElementById('canvas-size-display').textContent=CANVAS_W+' x '+CANVAS_H+' px'; updateZoomDisplay();

document.querySelectorAll('.ct-tab').forEach(tab=>tab.addEventListener('click',()=>{document.querySelectorAll('.ct-tab').forEach(t=>t.classList.remove('active'));document.querySelectorAll('.ct-tab-content').forEach(c=>c.classList.remove('active'));tab.classList.add('active');document.getElementById('tab-'+tab.dataset.tab)?.classList.add('active');}));
function openModal(id){document.getElementById(id)?.classList.add('open')} function closeModal(id){document.getElementById(id)?.classList.remove('open')}
function setValue(id,value){const el=document.getElementById(id); if(el) el.value=value ?? '';}
function showTab(name){document.querySelector(`.ct-tab[data-tab="${name}"]`)?.click();}
function copySelectedText(select,targetId){const opt=select.options[select.selectedIndex];setValue(targetId,opt&&opt.value!==''?(opt.dataset.certName||opt.text).trim():'')}
function showAjaxError(endpoint,msg){const el=document.getElementById(endpoint+'_error');if(el){el.style.display='block';el.textContent=msg}}
function clearAjaxError(endpoint){const el=document.getElementById(endpoint+'_error');if(el){el.style.display='none';el.textContent=''}}
function ajaxUrl(endpoint,params={}){const url=new URL(window.location.href);url.searchParams.set('ajax',endpoint);Object.keys(params).forEach(k=>url.searchParams.set(k,params[k]));return url.toString()}
async function loadDropdown(endpoint,selectId,label){const select=document.getElementById(selectId);if(!select)return;clearAjaxError(endpoint);select.innerHTML=`<option value="">Loading ${label}...</option>`;try{const res=await fetch(ajaxUrl(endpoint),{headers:{Accept:'application/json'},cache:'no-store',credentials:'same-origin'});const text=await res.text();let json;try{json=JSON.parse(text)}catch(e){select.innerHTML=`<option value="">Failed to load ${label}</option>`;showAjaxError(endpoint,'Invalid JSON response.');return}if(json.status!=='success'){select.innerHTML=`<option value="">Failed to load ${label}</option>`;showAjaxError(endpoint,json.message||'Failed to load records.');return}select.innerHTML=`<option value="">Select ${label}</option>`;(json.data||[]).forEach(item=>{const opt=document.createElement('option');opt.value=item.id||'';opt.textContent=item.name||'Unnamed';if(item.cert_name)opt.dataset.certName=item.cert_name;select.appendChild(opt)})}catch(e){select.innerHTML=`<option value="">Failed to load ${label}</option>`;showAjaxError(endpoint,e.message||'Network error.')}}
async function loadIntakes(){const select=document.getElementById('intake_select');if(!select)return;clearAjaxError('intakes');select.innerHTML='<option value="">Loading intakes...</option>';try{const res=await fetch(ajaxUrl('intakes'),{headers:{Accept:'application/json'},cache:'no-store',credentials:'same-origin'});const json=JSON.parse(await res.text());if(json.status!=='success'){select.innerHTML='<option value="">Failed to load intakes</option>';showAjaxError('intakes',json.message||'Failed');return}select.innerHTML='<option value="">Select Intake</option>';(json.data||[]).forEach(item=>{const opt=document.createElement('option');opt.value=item.id||'';opt.textContent=item.name||'Unnamed';if(item.date_from)opt.dataset.dateFrom=item.date_from;if(item.date_to)opt.dataset.dateTo=item.date_to;select.appendChild(opt)})}catch(e){select.innerHTML='<option value="">Failed to load intakes</option>';showAjaxError('intakes',e.message||'Network error.')}}
function fillIntakeDates(select){const opt=select.options[select.selectedIndex];setValue('course_date_from',opt?.dataset?.dateFrom||'');setValue('course_date_to',opt?.dataset?.dateTo||'');if(opt?.dataset?.dateFrom){setValue('cert_year',opt.dataset.dateFrom.slice(0,4));generateCertificateNo()}}
async function generateCertificateNo(){const year=document.getElementById('cert_year')?.value||new Date().getFullYear();setValue('certificate_no','Generating...');try{const res=await fetch(ajaxUrl('certificate_no',{year}),{headers:{Accept:'application/json'},cache:'no-store',credentials:'same-origin'});const json=JSON.parse(await res.text());setValue('certificate_no',json.status==='success'?json.certificate_no:'')}catch(e){setValue('certificate_no','')}}

function rteExec(cmd){const ed=document.getElementById('rte-editor');ed.focus();document.execCommand(cmd,false,null);updateRteToolbarState()}
function rteInsertBreak(){const ed=document.getElementById('rte-editor');ed.focus();const sel=window.getSelection();if(!sel.rangeCount)return;const range=sel.getRangeAt(0);range.deleteContents();const br=document.createElement('br');range.insertNode(br);range.setStartAfter(br);range.setEndAfter(br);sel.removeAllRanges();sel.addRange(range)}
function rteKeydown(e){if(e.key==='Enter'){e.preventDefault();rteInsertBreak()}}
function updateRteToolbarState(){['bold','italic','underline'].forEach(cmd=>{const btn=document.getElementById('rte-'+cmd+'-btn');if(btn)btn.classList.toggle('active',document.queryCommandState(cmd))})}
function applyTextTransform(text,type){if(type==='upper')return text.toUpperCase();if(type==='lower')return text.toLowerCase();if(type==='sentence')return text.toLowerCase().replace(/(^\s*\w|[.!?]\s+\w)/g,c=>c.toUpperCase());if(type==='title')return text.toLowerCase().split(' ').map((w,i)=>w? w.charAt(0).toUpperCase()+w.slice(1):w).join(' ');return text}
function transformEditorText(node,type){node.childNodes.forEach(ch=>{if(ch.nodeType===Node.TEXT_NODE)ch.textContent=applyTextTransform(ch.textContent,type);else if(ch.nodeType===Node.ELEMENT_NODE&&ch.tagName!=='BR')transformEditorText(ch,type)})}
function rteTransform(type){transformEditorText(document.getElementById('rte-editor'),type)}
function getRteHtml(){const clone=document.getElementById('rte-editor').cloneNode(true);while(clone.lastChild&&clone.lastChild.nodeName==='BR')clone.removeChild(clone.lastChild);return clone.innerHTML.trim()}
function addRichText(){const ed=document.getElementById('rte-editor'), html=getRteHtml();if(!html||html==='<br>')return;addElement({type:'text',field:null,html,text:ed.innerText,x:80,y:160+elements.length*48,w:360,h:55,style:{fontSize:'18px',fontWeight:'400',fontStyle:'normal',textDecoration:'none',color:'#333333',fontFamily:"'Segoe UI',sans-serif",textAlign:'left',lineHeight:'1.4'}});ed.innerHTML=''}

function fileToDataURL(file){return new Promise((resolve,reject)=>{const r=new FileReader();r.onload=e=>resolve(e.target.result);r.onerror=reject;r.readAsDataURL(file)})}
async function pdfToImageDataURL(file){if(typeof pdfjsLib==='undefined')throw new Error('PDF.js not loaded.');const pdf=await pdfjsLib.getDocument({data:await file.arrayBuffer()}).promise;const page=await pdf.getPage(1);const viewport=page.getViewport({scale:2.5});const c=document.createElement('canvas'),ctx=c.getContext('2d');c.width=viewport.width;c.height=viewport.height;await page.render({canvasContext:ctx,viewport}).promise;return c.toDataURL('image/png',1)}
async function loadBackground(input){const file=input.files[0];if(!file)return;const txt=document.querySelector('#bg-upload-zone .upload-zone-text');if(txt)txt.textContent='Loading '+file.name+'...';try{const isPdf=file.type==='application/pdf'||file.name.toLowerCase().endsWith('.pdf');bgDataUrl=isPdf?await pdfToImageDataURL(file):await fileToDataURL(file);currentBgPath='';canvasBg.src=bgDataUrl;canvasBg.style.display='block';if(txt)txt.textContent=file.name}catch(e){alert('Failed to load file.');clearBackground()}}
function clearBackground(){bgDataUrl='';currentBgPath='';canvasBg.src='';canvasBg.style.display='none';const f=document.getElementById('bg-file');if(f)f.value='';const txt=document.querySelector('#bg-upload-zone .upload-zone-text');if(txt)txt.textContent='Click or drag JPG, PNG, WEBP, or PDF'}
function setBackgroundFromPath(path){bgDataUrl='';currentBgPath=path||'';if(currentBgPath){canvasBg.src=currentBgPath;canvasBg.style.display='block';const txt=document.querySelector('#bg-upload-zone .upload-zone-text');if(txt)txt.textContent=currentBgPath.split('/').pop()}else clearBackground()}

function addLogos(input){Array.from(input.files).forEach(file=>{if(!file.type.startsWith('image/'))return;const reader=new FileReader();reader.onload=e=>{logoStore.push({id:'logo_'+Date.now()+'_'+Math.random().toString(36).slice(2),name:file.name,src:e.target.result});renderLogoStrip()};reader.readAsDataURL(file)});input.value=''}
function renderLogoStrip(){const strip=document.getElementById('logo-strip');strip.innerHTML='';logoStore.forEach(logo=>{const t=document.createElement('div');t.className='logo-thumb';t.draggable=true;t.dataset.logoId=logo.id;t.innerHTML=`<img src="${logo.src}" alt=""><button class="logo-thumb-del" type="button" onclick="removeLogo('${logo.id}')">x</button>`;t.addEventListener('dragstart',e=>{e.dataTransfer.setData('text/plain',logo.id);e.dataTransfer.setData('type','logo')});strip.appendChild(t)})}
function removeLogo(id){logoStore=logoStore.filter(l=>l.id!==id);renderLogoStrip()}
canvas.addEventListener('dragover',e=>{e.preventDefault();canvas.classList.add('accepting')});canvas.addEventListener('dragleave',()=>canvas.classList.remove('accepting'));canvas.addEventListener('drop',e=>{e.preventDefault();canvas.classList.remove('accepting');const logo=logoStore.find(l=>l.id===e.dataTransfer.getData('text/plain'));if(!logo)return;const rect=canvas.getBoundingClientRect();addElement({type:'logo',logoId:logo.id,logoSrc:logo.src,x:Math.max(0,(e.clientX-rect.left)*(CANVAS_W/rect.width)-50),y:Math.max(0,(e.clientY-rect.top)*(CANVAS_H/rect.height)-25),w:120,h:70})});
function addTextField(tagEl){const field=tagEl.dataset.field;addElement({type:'text',field,html:null,text:field,x:80,y:160+elements.length*38,w:330,h:40,style:{fontSize:'22px',fontWeight:'700',fontStyle:'normal',textDecoration:'none',color:'#222222',fontFamily:"'Segoe UI',sans-serif",textAlign:'left',lineHeight:'1.4'}})}
function addElement(opts){const id=opts.id||('el_'+(++elemIdCounter));const el=Object.assign({id,w:150,h:40},opts);elements.push(el);renderElement(el);selectElement(id)}
function renderElement(el){const node=document.createElement('div');node.id='ce_'+el.id;node.className='ce-elem';node.style.left=el.x+'px';node.style.top=el.y+'px';node.style.width=el.w+'px';node.style.height=el.h+'px';node.dataset.id=el.id;
 if(el.type==='logo'){node.innerHTML=`<img class="ce-logo" src="${el.logoSrc||''}" alt="">`;}else{const st=el.style||{};node.innerHTML=`<div class="ce-text"></div>`;const tx=node.querySelector('.ce-text');tx.innerHTML=el.html || escapeHtml(el.text||'');Object.assign(tx.style,{fontSize:st.fontSize||'22px',fontWeight:st.fontWeight||'700',fontStyle:st.fontStyle||'normal',textDecoration:st.textDecoration||'none',color:st.color||'#222',fontFamily:st.fontFamily||"'Segoe UI',sans-serif",textAlign:st.textAlign||'left',lineHeight:st.lineHeight||'1.4'});}
 node.innerHTML += `<button type="button" class="ce-elem-del">x</button><div class="ce-resize"></div>`;
 node.addEventListener('mousedown',startMove);node.querySelector('.ce-elem-del').onclick=e=>{e.stopPropagation();selectElement(el.id);deleteSelected()};node.querySelector('.ce-resize').addEventListener('mousedown',startResize);canvas.appendChild(node)}
function escapeHtml(s){return String(s).replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]))}
function selectElement(id){selectedId=id;document.querySelectorAll('.ce-elem').forEach(n=>n.classList.toggle('selected',n.dataset.id===id));const el=elements.find(e=>e.id===id);document.getElementById('prop-panel-section').style.display=el?'block':'none';if(!el)return;const textRows=['pp-case-row','pp-bold-row'];textRows.forEach(r=>{const x=document.getElementById(r);if(x)x.style.display=el.type==='text'?'flex':'none'});setValue('pp-width',Math.round(el.w));setValue('pp-height',Math.round(el.h));if(el.type==='text'){const s=el.style||{};setValue('pp-fontsize',parseInt(s.fontSize||22));setValue('pp-color',s.color||'#222222');setValue('pp-color-hex',s.color||'#222222');setValue('pp-weight',s.fontWeight||'700');setValue('pp-family',s.fontFamily||"'Segoe UI',sans-serif");setValue('pp-align',s.textAlign||'left');setValue('pp-lineheight',s.lineHeight||'1.4');document.getElementById('pp-bold-toggle')?.classList.toggle('active',['700','900','bold'].includes(String(s.fontWeight)));document.getElementById('pp-italic-toggle')?.classList.toggle('active',s.fontStyle==='italic');document.getElementById('pp-underline-toggle')?.classList.toggle('active',s.textDecoration==='underline')}}
function syncElementFromDom(el){const node=document.getElementById('ce_'+el.id);if(!node)return;el.x=parseFloat(node.style.left)||0;el.y=parseFloat(node.style.top)||0;el.w=parseFloat(node.style.width)||el.w;el.h=parseFloat(node.style.height)||el.h;if(el.type==='text'){const tx=node.querySelector('.ce-text');if(tx){el.html=tx.innerHTML;el.text=tx.innerText;}}}
function startMove(e){if(e.target.classList.contains('ce-resize')||e.target.classList.contains('ce-elem-del'))return;const node=e.currentTarget,id=node.dataset.id,el=elements.find(x=>x.id===id);selectElement(id);const sx=e.clientX,sy=e.clientY,ox=el.x,oy=el.y;function mm(ev){el.x=Math.max(0,Math.min(CANVAS_W-el.w,ox+(ev.clientX-sx)/zoom));el.y=Math.max(0,Math.min(CANVAS_H-el.h,oy+(ev.clientY-sy)/zoom));node.style.left=el.x+'px';node.style.top=el.y+'px'}function mu(){document.removeEventListener('mousemove',mm);document.removeEventListener('mouseup',mu)}document.addEventListener('mousemove',mm);document.addEventListener('mouseup',mu)}
function startResize(e){e.stopPropagation();const node=e.currentTarget.parentElement,id=node.dataset.id,el=elements.find(x=>x.id===id);selectElement(id);const sx=e.clientX,sy=e.clientY,ow=el.w,oh=el.h;function mm(ev){el.w=Math.max(20,ow+(ev.clientX-sx)/zoom);el.h=Math.max(10,oh+(ev.clientY-sy)/zoom);node.style.width=el.w+'px';node.style.height=el.h+'px';setValue('pp-width',Math.round(el.w));setValue('pp-height',Math.round(el.h))}function mu(){document.removeEventListener('mousemove',mm);document.removeEventListener('mouseup',mu)}document.addEventListener('mousemove',mm);document.addEventListener('mouseup',mu)}
function applyProp(prop,value){if(!selectedId)return;const el=elements.find(e=>e.id===selectedId);if(!el||el.type!=='text')return;el.style=el.style||{};el.style[prop]=value;const tx=document.querySelector('#ce_'+selectedId+' .ce-text');if(tx)tx.style[prop]=value;selectElement(selectedId)}
function applyElemSize(dim,value){if(!selectedId)return;const el=elements.find(e=>e.id===selectedId),node=document.getElementById('ce_'+selectedId);if(!el||!node)return;value=parseFloat(value)||0;if(dim==='width'){el.w=value;node.style.width=value+'px'}else{el.h=value;node.style.height=value+'px'}}
function applyCase(type){if(!selectedId)return;const el=elements.find(e=>e.id===selectedId);if(!el||el.type!=='text')return;const tx=document.querySelector('#ce_'+selectedId+' .ce-text');if(tx){transformEditorText(tx,type);el.html=tx.innerHTML;el.text=tx.innerText}}
function togglePropBold(){const el=elements.find(e=>e.id===selectedId);if(!el||el.type!=='text')return;el.style=el.style||{};el.style.fontWeight=(['700','900','bold'].includes(String(el.style.fontWeight)))?'400':'700';applyProp('fontWeight',el.style.fontWeight);setValue('pp-weight',el.style.fontWeight)}
function togglePropStyle(prop,onVal,offVal){const el=elements.find(e=>e.id===selectedId);if(!el||el.type!=='text')return;el.style=el.style||{};el.style[prop]=el.style[prop]===onVal?offVal:onVal;applyProp(prop,el.style[prop])}
function deleteSelected(){if(!selectedId)return;document.getElementById('ce_'+selectedId)?.remove();elements=elements.filter(e=>e.id!==selectedId);selectedId=null;document.getElementById('prop-panel-section').style.display='none'}
function clearCanvas(){elements=[];selectedId=null;document.querySelectorAll('.ce-elem').forEach(n=>n.remove());document.getElementById('prop-panel-section').style.display='none'}
function updateZoomDisplay(){document.getElementById('zoom-display').textContent=Math.round(zoom*100)+'%';document.getElementById('canvas-scale-wrapper').style.transform=`scale(${zoom})`}
function zoomIn(){zoom=Math.min(zoom+.1,2.5);updateZoomDisplay()} function zoomOut(){zoom=Math.max(zoom-.1,.3);updateZoomDisplay()} function fitCanvas(){const main=document.querySelector('.ct-main');zoom=Math.min((main.clientWidth-80)/CANVAS_W,1);updateZoomDisplay()}

function getLayoutJSON(){elements.forEach(syncElementFromDom);return JSON.stringify({canvas:{w:CANVAS_W,h:CANVAS_H},elements:elements.map(el=>({id:el.id,type:el.type,field:el.field||null,text:el.text||null,html:el.html||null,x:el.x,y:el.y,w:el.w,h:el.h,style:el.style||{},logoId:el.logoId||null,logoSrc:el.logoSrc||null}))})}
function injectLayoutData(){setValue('save-layout-json',getLayoutJSON());setValue('save-logos-json',JSON.stringify(logoStore));setValue('save-bg-data-url',bgDataUrl)}
function prepareGenerate(){setValue('gen-layout-json',getLayoutJSON());setValue('gen-bg-data',bgDataUrl);setValue('gen-canvas-w',CANVAS_W);setValue('gen-canvas-h',CANVAS_H)}
function openSaveModal(){injectLayoutData();const editId=document.getElementById('editing-template-id').value;document.getElementById('save-modal-title').textContent=editId?'Update Template':'Save Template';document.getElementById('save-submit-label').textContent=editId?'Update Template':'Save Template';openModal('saveModal')}
function normalizeFilePath(path){path=(path||'').trim();if(!path)return'';if(path.startsWith('http')||path.startsWith('data:'))return path;return path.replace(/^\/+/,'')}
function loadTemplate(id,t){newTemplate(false);setValue('editing-template-id',id);setValue('save-template-id',id);setValue('save-template-name',t.template_name||'');setValue('save-title',t.title||'Certificate of Completion');setValue('save-body-text',t.body_text||'');setValue('save-footer-text',t.footer_text||'');setValue('save-status',t.status||'active');document.getElementById('save-is-default').checked=String(t.is_default||'0')==='1';setValue('existing-background-path',t.background_path||'');setValue('existing-logo-path',t.logo_path||'');setValue('existing-signature-path',t.signature_path||'');document.getElementById('editing-status').textContent='Editing: '+(t.template_name||('Template #'+id));document.getElementById('top-save-label').textContent='Update Template';if(t.background_path)setBackgroundFromPath(normalizeFilePath(t.background_path));
 try{const logos=JSON.parse(t.logos_json||'[]');logoStore=Array.isArray(logos)?logos.map(l=>({id:l.id||('logo_'+Date.now()),name:l.name||'Saved logo',src:normalizeFilePath(l.src||l.path||'')})):[]}catch(e){logoStore=[]}renderLogoStrip();
 try{const layout=JSON.parse(t.layout_json||'{}');if(layout.canvas){/* fixed canvas */}if(Array.isArray(layout.elements)){layout.elements.forEach(el=>addElement({id:el.id,type:el.type,field:el.field,text:el.text,html:el.html,x:parseFloat(el.x)||0,y:parseFloat(el.y)||0,w:parseFloat(el.w)||150,h:parseFloat(el.h)||40,style:Object.assign({fontSize:'22px',fontWeight:'700',fontStyle:'normal',textDecoration:'none',color:'#222222',fontFamily:"'Segoe UI',sans-serif",textAlign:'left',lineHeight:'1.4'},el.style||{}),logoId:el.logoId,logoSrc:normalizeFilePath(el.logoSrc||'')}));}}catch(e){console.error(e)}
 selectedId=null;document.querySelectorAll('.ce-elem').forEach(n=>n.classList.remove('selected'));document.getElementById('prop-panel-section').style.display='none';showTab('design');}
function newTemplate(clearBg=true){clearCanvas();logoStore=[];renderLogoStrip();if(clearBg)clearBackground();setValue('editing-template-id','');setValue('save-template-id','');setValue('save-template-name','');setValue('save-title','Certificate of Completion');setValue('save-body-text','');setValue('save-footer-text','Issued by Women in Technology Uganda (WITU)');setValue('save-status','active');setValue('existing-background-path','');setValue('existing-logo-path','');setValue('existing-signature-path','');document.getElementById('save-is-default').checked=false;document.getElementById('editing-status').textContent='Creating new template.';document.getElementById('top-save-label').textContent='Save Template'}
function confirmDelete(id){setValue('delete-id-field',id);openModal('deleteModal')}
document.addEventListener('click',e=>{if(!canvas.contains(e.target)&&!e.target.closest('.prop-panel')){}});
loadDropdown('students','student_select','Student');loadDropdown('courses','course_select','Course');loadIntakes();generateCertificateNo();
</script>

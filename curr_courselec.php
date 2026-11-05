<?php
declare(strict_types=1);
$systemname=$systemname??'';
$role=(string)($_SESSION[$systemname.'letype']??($_SESSION['role']??''));
$user_uid=(string)($_SESSION[$systemname.'leido']??($_SESSION['unique_id']??''));
if(!isset($conn)||!($conn instanceof mysqli)){echo '<div class="app-alert app-alert-danger">Database connection not available.</div>';return;}
if(!function_exists('h')){function h($v):string{return htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}}
function cmj($v):string{return htmlspecialchars(json_encode((string)$v),ENT_QUOTES,'UTF-8');}
function cmTableExists(mysqli $conn,string $table):bool{
    static $cache=[];
    if(array_key_exists($table,$cache)) return $cache[$table];
    $q=$conn->prepare("SELECT COUNT(*) total FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? LIMIT 1");
    if(!$q) return $cache[$table]=false;
    $q->bind_param('s',$table);$q->execute();$row=$q->get_result()->fetch_assoc();$q->close();
    return $cache[$table]=((int)($row['total']??0)>0);
}
function cmColumnExists(mysqli $conn,string $table,string $column):bool{
    static $cache=[]; $key=$table.'.'.$column;
    if(array_key_exists($key,$cache)) return $cache[$key];
    $q=$conn->prepare("SELECT COUNT(*) total FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=? LIMIT 1");
    if(!$q) return $cache[$key]=false;
    $q->bind_param('ss',$table,$column);$q->execute();$row=$q->get_result()->fetch_assoc();$q->close();
    return $cache[$key]=((int)($row['total']??0)>0);
}
function cmPrepareOrError(mysqli $conn,string $sql,string $label):mysqli_stmt|false{
    $stmt=$conn->prepare($sql);
    if(!$stmt){echo '<div class="app-alert app-alert-danger"><i class="fas fa-exclamation-circle"></i> '.h($label).': '.h($conn->error).'</div>';return false;}
    return $stmt;
}
$cx=(int)($_GET['course']??0); $intaken=(int)($_GET['ausydfgasuidgipppuwqu']??0);
if($cx<=0||$user_uid===''){echo '<div class="app-alert app-alert-danger">Invalid course or expired session.</div>';return;}
$valid=$role==='xybrtw';
if(!$valid&&$role==='xtfbry'){$s=$conn->prepare('SELECT 1 FROM course_registration_lecturers WHERE user_id=? AND course_id=? LIMIT 1');if($s){$s->bind_param('si',$user_uid,$cx);$s->execute();$valid=$s->get_result()->num_rows>0;$s->close();}}
if(!$valid){echo '<div class="app-alert app-alert-danger">Access denied.</div>';return;}
$s=cmPrepareOrError($conn,'SELECT id,name,category FROM courses WHERE id=? LIMIT 1','Unable to load course');if(!$s)return;$s->bind_param('i',$cx);$s->execute();$course=$s->get_result()->fetch_assoc();$s->close();if(!$course){echo '<div class="app-alert app-alert-danger">Course not found.</div>';return;}
$modules=[];$hasAccessMode=cmColumnExists($conn,'course_unit','access_mode');$moduleSql=$hasAccessMode?"SELECT id,name,order_number,COALESCE(access_mode,'immediate') access_mode FROM course_unit WHERE course_id=? ORDER BY order_number,id":"SELECT id,name,order_number,'immediate' access_mode FROM course_unit WHERE course_id=? ORDER BY order_number,id";$s=cmPrepareOrError($conn,$moduleSql,'Unable to load course modules');if(!$s)return;$s->bind_param('i',$cx);$s->execute();$r=$s->get_result();while($r&&$x=$r->fetch_assoc())$modules[]=$x;$s->close();
$intakes=[];$r=$conn->query("SELECT i.id,i.name,i.date_launching,COALESCE(b.name,'No Branch') branch_name FROM intakes i LEFT JOIN branches b ON b.id=i.branch_id ORDER BY i.date_launching DESC,i.id DESC");while($r&&$x=$r->fetch_assoc())$intakes[]=$x;
$hasTopics=cmTableExists($conn,'course_topics');$hasMaterials=cmTableExists($conn,'topic_materials');$hasQuizzes=cmTableExists($conn,'module_quizzes');$hasQuizQuestions=cmTableExists($conn,'quiz_questions');$hasHandedIn=cmTableExists($conn,'handed_in');
?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="app.css">
<style>
.cm-actions{display:flex;gap:8px;flex-wrap:wrap}.cm-tabs{display:flex;gap:4px;border-bottom:1px solid var(--border);overflow:auto;margin:14px 0}.cm-tab{border:0;background:transparent;padding:10px 14px;cursor:pointer;font:inherit;color:var(--muted);border-bottom:3px solid transparent}.cm-tab.active{color:var(--maroon);border-bottom-color:var(--maroon);font-weight:700}.cm-panel{display:none}.cm-panel.active{display:block}.topic-card,.assignment-card,.quiz-card{border:1px solid var(--border);border-left:4px solid var(--maroon);border-radius:10px;padding:14px;background:var(--surface);margin-bottom:12px}.assignment-card{border-left-color:var(--gold)}.quiz-card{border-left-color:#4267b2}.topic-head{display:flex;justify-content:space-between;gap:12px;align-items:flex-start}.topic-title{font-weight:700}.topic-meta{font-size:.92rem;color:var(--muted);margin-top:4px}.material-list{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:8px;margin-top:12px}.material-item{border:1px solid var(--border);border-radius:8px;padding:10px;background:var(--surface2)}.material-item .mi-title{font-size:.96rem;font-weight:600}.modal-overlay{position:fixed;inset:0;background:rgba(15,23,42,.58);z-index:99999;display:none;align-items:flex-start;justify-content:center;padding:24px 12px;overflow:auto}.modal-overlay.is-open{display:flex}.modal-box{background:#fff;width:min(760px,100%);border-radius:12px;box-shadow:0 20px 60px rgba(0,0,0,.25);overflow:hidden}.modal-head,.modal-foot{padding:14px 18px;display:flex;align-items:center;justify-content:space-between}.modal-head{background:var(--maroon);color:#fff}.modal-body{padding:18px}.modal-foot{border-top:1px solid var(--border);justify-content:flex-end;gap:8px}.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}.field label{display:block;font-size:.92rem;font-weight:600;margin-bottom:5px}.field input,.field select,.field textarea{width:100%;padding:9px 10px;border:1px solid var(--border);border-radius:7px}.span-2{grid-column:1/-1}.resource-row{border:1px dashed var(--border);border-radius:8px;padding:10px;margin:8px 0}.cm-btn{border:0;border-radius:7px;padding:8px 12px;cursor:pointer;font-weight:600}.cm-primary{background:var(--maroon);color:white}.cm-gold{background:var(--gold);color:white}.cm-soft{background:var(--surface2);color:var(--text);border:1px solid var(--border)}.cm-danger{background:#b42318;color:white}@media(max-width:650px){.form-grid{grid-template-columns:1fr}.span-2{grid-column:auto}}

/* Custom notification + confirmation popups */
.cm-popup-overlay{
    position:fixed;
    inset:0;
    z-index:120000;
    display:none;
    align-items:center;
    justify-content:center;
    padding:20px;
    background:rgba(15,23,42,.58);
    backdrop-filter:blur(2px);
}
.cm-popup-overlay.is-open{display:flex;}
.cm-popup{
    width:min(430px,100%);
    background:#fff;
    border-radius:14px;
    box-shadow:0 24px 70px rgba(0,0,0,.28);
    overflow:hidden;
    animation:cmPopupIn .18s ease-out;
}
.cm-popup-head{
    display:flex;
    align-items:center;
    gap:12px;
    padding:18px 20px 10px;
}
.cm-popup-icon{
    width:44px;
    height:44px;
    border-radius:50%;
    display:grid;
    place-items:center;
    flex:0 0 44px;
    font-size:1.15rem;
}
.cm-popup-icon.success{
    background:#eaf7ef;
    color:#198754;
}
.cm-popup-icon.error{
    background:#fdecec;
    color:#b42318;
}
.cm-popup-icon.warning{
    background:#fff4d6;
    color:#b7791f;
}
.cm-popup-title{
    font-size:1rem;
    font-weight:700;
    color:var(--text);
}
.cm-popup-body{
    padding:4px 20px 18px;
    color:var(--muted);
    font-size:.86rem;
    line-height:1.55;
}
.cm-popup-foot{
    display:flex;
    justify-content:flex-end;
    gap:8px;
    padding:14px 20px 18px;
    border-top:1px solid var(--border);
    background:var(--surface2);
}
.cm-popup-btn{
    border:0;
    border-radius:8px;
    padding:9px 15px;
    cursor:pointer;
    font-weight:700;
    font-family:inherit;
}
.cm-popup-btn.primary{
    background:var(--maroon);
    color:#fff;
}
.cm-popup-btn.danger{
    background:#b42318;
    color:#fff;
}
.cm-popup-btn.soft{
    background:#fff;
    color:var(--text);
    border:1px solid var(--border);
}
@keyframes cmPopupIn{
    from{opacity:0;transform:translateY(8px) scale(.98)}
    to{opacity:1;transform:translateY(0) scale(1)}
}


/* Scrollable content modals */
.modal-overlay{
    overflow-y:auto !important;
    overflow-x:hidden !important;
    overscroll-behavior:contain;
    -webkit-overflow-scrolling:touch;
}
.modal-box{
    max-height:calc(100vh - 48px) !important;
    display:flex !important;
    flex-direction:column !important;
    overflow:hidden !important;
}
.modal-box > form{
    display:flex;
    flex-direction:column;
    min-height:0;
    max-height:calc(100vh - 48px);
}
.modal-box .modal-head{
    flex:0 0 auto;
}
.modal-box .modal-body{
    flex:1 1 auto;
    min-height:0;
    overflow-y:auto !important;
    overflow-x:hidden !important;
    -webkit-overflow-scrolling:touch;
    overscroll-behavior:contain;
}
.modal-box .modal-foot{
    flex:0 0 auto;
    position:sticky;
    bottom:0;
    z-index:3;
    background:#fff;
}
@media(max-width:650px){
    .modal-overlay{padding:10px 8px !important;}
    .modal-box,
    .modal-box > form{
        max-height:calc(100vh - 20px) !important;
    }
}


/* Larger readable typography across course management */
.page,
.page div,
.page span,
.page p,
.page a,
.page button,
.page input,
.page select,
.page textarea{
    font-size:inherit;
}

.topic-title{
    font-size:1.05rem !important;
    line-height:1.4;
}

.topic-meta{
    font-size:.94rem !important;
    line-height:1.55;
}

.material-item .mi-title{
    font-size:.98rem !important;
    line-height:1.45;
}

.material-item{
    font-size:.94rem;
}

.cm-tab{
    font-size:.96rem !important;
}

.cm-btn{
    font-size:.92rem !important;
}

.form-grid .field label{
    font-size:.92rem !important;
}

.form-grid .field input,
.form-grid .field select,
.form-grid .field textarea{
    font-size:.95rem !important;
}

.meta-pill,
.heading-meta,
.course-code,
.card-footer,
.cat-tag{
    font-size:.9rem !important;
}

.assignment-card .topic-meta,
.quiz-card .topic-meta{
    font-size:.94rem !important;
}

.assignment-card .topic-title,
.quiz-card .topic-title{
    font-size:1.02rem !important;
}

/* Material preview modal */
.preview-overlay{
    position:fixed;
    inset:0;
    z-index:125000;
    display:none;
    align-items:center;
    justify-content:center;
    padding:18px;
    background:rgba(15,23,42,.68);
}
.preview-overlay.is-open{
    display:flex;
}
.preview-box{
    width:min(1100px,98vw);
    height:min(88vh,900px);
    background:#fff;
    border-radius:14px;
    overflow:hidden;
    box-shadow:0 24px 70px rgba(0,0,0,.35);
    display:flex;
    flex-direction:column;
}
.preview-head{
    flex:0 0 auto;
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:12px;
    padding:14px 18px;
    background:var(--maroon);
    color:#fff;
}
.preview-head strong{
    font-size:1rem;
}
.preview-body{
    flex:1 1 auto;
    min-height:0;
    overflow:auto;
    background:#f3f4f6;
    display:flex;
    align-items:center;
    justify-content:center;
}
.preview-body iframe{
    width:100%;
    height:100%;
    border:0;
    background:#fff;
}
.preview-body img{
    max-width:100%;
    max-height:100%;
    object-fit:contain;
}
.preview-body video{
    width:100%;
    max-height:100%;
    background:#000;
}
.preview-body audio{
    width:min(700px,90%);
}
.preview-close{
    border:0;
    background:#fff;
    color:var(--maroon);
    width:36px;
    height:36px;
    border-radius:8px;
    cursor:pointer;
    font-size:1rem !important;
    font-weight:700;
}
.preview-message{
    padding:20px;
    font-size:.95rem;
    color:var(--muted);
    text-align:center;
}


.material-item .cm-btn{
    font-size:.94rem !important;
}


/* Instructor engagement visibility */
.material-item .cm-actions{
    margin-top:10px !important;
}
.material-item .cm-btn{
    font-size:.94rem !important;
}
.material-item .mi-title{
    font-size:1rem !important;
}
.topic-meta{
    font-size:.94rem !important;
    line-height:1.5;
}
.topic-title{
    font-size:1.06rem !important;
}
.assignment-card .topic-meta,
.quiz-card .topic-meta{
    font-size:.94rem !important;
}
.assignment-card .topic-title,
.quiz-card .topic-title{
    font-size:1.03rem !important;
}
.cm-tab,
.cm-btn,
.form-grid .field label,
.form-grid .field input,
.form-grid .field select,
.form-grid .field textarea{
    font-size:.94rem !important;
}


/* Assignment layout: 4 per row on desktop */
.assignment-grid{
    display:grid !important;
    grid-template-columns:repeat(4,minmax(0,1fr)) !important;
    gap:14px !important;
}
.assignment-grid .assignment-card{min-width:0}

/* My Courses tab */
.my-courses-tab{
    display:inline-flex;
    align-items:center;
    gap:7px;
    padding:9px 14px;
    border:1px solid var(--border);
    border-radius:8px;
    background:var(--surface);
    color:var(--maroon);
    text-decoration:none;
    font-size:.94rem !important;
    font-weight:700;
    white-space:nowrap;
}
.my-courses-tab:hover{background:var(--surface2)}
@media(max-width:1200px){.assignment-grid{grid-template-columns:repeat(3,minmax(0,1fr)) !important}}
@media(max-width:900px){.assignment-grid{grid-template-columns:repeat(2,minmax(0,1fr)) !important}}
@media(max-width:600px){.assignment-grid{grid-template-columns:1fr !important}}


/* Clickable module tabs */
.dash-tab-bar{
    display:flex;
    gap:4px;
    overflow-x:auto;
    scrollbar-width:thin;
}
.dash-tab-btn{
    cursor:pointer !important;
    user-select:none;
    pointer-events:auto !important;
    display:inline-flex;
    align-items:center;
    gap:7px;
    white-space:nowrap;
}
.dash-tab-btn:hover{
    background:var(--surface2);
    color:var(--maroon);
}
.dash-tab-btn:focus-visible{
    outline:2px solid var(--gold);
    outline-offset:2px;
}


/* Assignments and quizzes: 4 per row on desktop */
.assignment-grid,
.quiz-grid{
    display:grid !important;
    grid-template-columns:repeat(4,minmax(0,1fr)) !important;
    gap:14px !important;
    align-items:stretch;
}

.assignment-grid .assignment-card,
.quiz-grid .quiz-card{
    margin-bottom:0 !important;
    min-width:0;
    height:100%;
}

.assignment-grid .topic-head,
.quiz-grid .topic-head{
    height:100%;
    display:flex;
    flex-direction:column;
    align-items:stretch;
    justify-content:space-between;
    gap:12px;
}

.assignment-grid .cm-actions,
.quiz-grid .cm-actions{
    margin-top:auto;
}

.assignment-grid .cm-btn,
.quiz-grid .cm-btn{
    width:100%;
    justify-content:center;
    text-align:center;
    display:inline-flex;
    align-items:center;
    gap:6px;
}

.assignment-grid .topic-title,
.quiz-grid .topic-title{
    font-size:1rem !important;
    line-height:1.4;
}

.assignment-grid .topic-meta,
.quiz-grid .topic-meta{
    font-size:.9rem !important;
    line-height:1.5;
}

@media(max-width:1250px){
    .assignment-grid,
    .quiz-grid{
        grid-template-columns:repeat(3,minmax(0,1fr)) !important;
    }
}

@media(max-width:900px){
    .assignment-grid,
    .quiz-grid{
        grid-template-columns:repeat(2,minmax(0,1fr)) !important;
    }
}

@media(max-width:600px){
    .assignment-grid,
    .quiz-grid{
        grid-template-columns:1fr !important;
    }
}

</style>
<nav class="topbar"><div class="topbar-left"><h1><i class="fas fa-book-open"></i> <?php echo h($course['name']); ?> - Course Management</h1></div><div class="topbar-right"><i class="fas fa-clock"></i> <?php echo date('h:i a'); ?></div></nav>
<div style="padding:12px 18px 0;">
    <a class="my-courses-tab" href="?view=courses">
        <i class="fas fa-arrow-left"></i> My Courses
    </a>
</div>
<main class="page">
  <?php if(!$hasAccessMode):?><div class="app-alert app-alert-warning"><i class="fas fa-info-circle"></i> Module locking is temporarily disabled because <code>course_unit.access_mode</code> is missing. The page is using compatibility mode.</div><?php endif;?>
  <?php if(!$hasTopics||!$hasMaterials||!$hasQuizzes):?><div class="app-alert app-alert-warning"><i class="fas fa-database"></i> Some new course-structure tables are not available yet. Run the course restructure migration to enable Topics, Materials and Quizzes.</div><?php endif;?>
  <div class="controls-bar" style="margin-bottom:18px"><div class="ctrl-field" style="max-width:420px;flex:1"><label class="ctrl-label">Select Intake</label><select class="ctrl-select" id="cmIntake" onchange="changeIntake(this.value)"><option value="">- Select intake -</option><?php foreach($intakes as $i):?><option value="<?php echo (int)$i['id'];?>" <?php echo (int)$i['id']===$intaken?'selected':'';?>><?php echo h($i['name'].' - '.$i['branch_name']);?></option><?php endforeach;?></select></div></div>
  <?php if($intaken<=0):?><div class="empty"><i class="fas fa-calendar-alt"></i><p>Select an intake to manage topics, materials, assignments and quizzes.</p></div><?php elseif(!$modules):?><div class="empty"><p>No modules found.</p></div><?php else:?>
  <div class="dash-content-card">
  <div class="dash-tab-bar" id="moduleTabs">
    <?php foreach($modules as $mi=>$m): ?>
      <button
        type="button"
        class="dash-tab-btn <?php echo $mi===0?'active':''; ?>"
        data-module-index="<?php echo $mi; ?>"
        data-module-id="<?php echo (int)$m['id']; ?>"
        onclick="showModule(<?php echo $mi; ?>, this, <?php echo (int)$m['id']; ?>)"
        title="Open <?php echo h($m['name']); ?>"
        aria-controls="cmp-<?php echo $mi; ?>"
        aria-selected="<?php echo $mi===0?'true':'false'; ?>"
      >
        <i class="fas fa-cube"></i>
        <span><?php echo h($m['name']); ?></span>
      </button>
    <?php endforeach; ?>
  </div>
  <?php foreach($modules as $mi=>$m):$mid=(int)$m['id'];
    $topics=[];if($hasTopics){$topicCountSql=$hasMaterials?"(SELECT COUNT(*) FROM topic_materials tm WHERE tm.topic_id=t.id)":"0";$s=cmPrepareOrError($conn,"SELECT t.*,{$topicCountSql} material_count FROM course_topics t WHERE t.course_unit_id=? AND COALESCE(t.intake_id,0) IN (0,?) ORDER BY t.order_number,t.id",'Unable to load topics');if($s){$s->bind_param('ii',$mid,$intaken);$s->execute();$rr=$s->get_result();while($rr&&$x=$rr->fetch_assoc())$topics[]=$x;$s->close();}}
    $assign=[];$submissionCountSql=$hasHandedIn?"(SELECT COUNT(*) FROM handed_in hi WHERE hi.assignment_id=a.id)":"0";$s=cmPrepareOrError($conn,"SELECT a.*,{$submissionCountSql} submission_count FROM assignments a WHERE a.course_unit=? AND a.intake=? AND COALESCE(a.dummy,'no')='no' ORDER BY a.id DESC",'Unable to load assignments');if($s){$s->bind_param('ii',$mid,$intaken);$s->execute();$rr=$s->get_result();while($rr&&$x=$rr->fetch_assoc())$assign[]=$x;$s->close();}
    $quizzes=[];if($hasQuizzes){$questionCountSql=$hasQuizQuestions?"(SELECT COUNT(*) FROM quiz_questions qq WHERE qq.quiz_id=q.id)":"0";$s=cmPrepareOrError($conn,"SELECT q.*,{$questionCountSql} question_count FROM module_quizzes q WHERE q.course_unit_id=? AND COALESCE(q.intake_id,0) IN (0,?) ORDER BY q.id DESC",'Unable to load quizzes');if($s){$s->bind_param('ii',$mid,$intaken);$s->execute();$rr=$s->get_result();while($rr&&$x=$rr->fetch_assoc())$quizzes[]=$x;$s->close();}}
  ?>
  <section class="dash-tab-panel <?php echo $mi===0?'active':'';?>" id="cmp-<?php echo $mi;?>">
    <div class="topic-head" style="margin:14px 0"><div><div class="topic-title"><?php echo h($m['name']);?></div><div class="topic-meta"><?php echo count($topics);?> topics . <?php echo count($assign);?> assignments . <?php echo count($quizzes);?> quizzes</div></div><div class="cm-actions"><select class="cm-btn cm-soft" onchange="updateModuleAccess(<?php echo $mid;?>,this.value)" <?php echo !$hasAccessMode?'disabled title="Run migration to add course_unit.access_mode"':'';?>><option value="immediate" <?php echo ($m['access_mode']??'immediate')==='immediate'?'selected':'';?>>Available immediately</option><option value="after_previous" <?php echo ($m['access_mode']??'immediate')==='after_previous'?'selected':'';?>>Lock until previous module is completed</option></select><button class="cm-btn cm-primary" onclick="openCreateMenu(<?php echo $mid;?>,<?php echo cmj($m['name']);?>)"><i class="fas fa-plus"></i> Create</button></div></div>
    <div class="cm-tabs"><button class="cm-tab active" onclick="switchKind(<?php echo $mi;?>,'topics',this)">Topics <span class="pill"><?php echo count($topics);?></span></button><button class="cm-tab" onclick="switchKind(<?php echo $mi;?>,'assignments',this)">Assignments <span class="pill"><?php echo count($assign);?></span></button><button class="cm-tab" onclick="switchKind(<?php echo $mi;?>,'quizzes',this)">Quizzes <span class="pill"><?php echo count($quizzes);?></span></button></div>
    <div class="cm-panel active" id="kind-<?php echo $mi;?>-topics">
      <?php if(!$topics):?><div class="empty"><p>No topics yet. Use Create ? Topic / Lesson.</p></div><?php endif;?>
      <?php foreach($topics as $t):$tid=(int)$t['id'];$materials=[];if($hasMaterials){$s=cmPrepareOrError($conn,'SELECT * FROM topic_materials WHERE topic_id=? ORDER BY order_number,id','Unable to load topic materials');if($s){$s->bind_param('i',$tid);$s->execute();$mr=$s->get_result();while($mr&&$x=$mr->fetch_assoc())$materials[]=$x;$s->close();}}?>
      <div class="topic-card"><div class="topic-head"><div><div class="topic-title"><?php echo h($t['title']);?></div><div class="topic-meta"><?php echo count($materials);?> material(s) . <?php echo !empty($t['is_required'])?'Required':'Optional';?> . <?php echo h(ucfirst($t['status']));?></div><?php if(!empty($t['description'])):?><div class="topic-meta" style="margin-top:7px"><?php echo h($t['description']);?></div><?php endif;?></div><div class="cm-actions"><button class="cm-btn cm-gold" onclick="openMaterialModal(<?php echo $tid;?>,<?php echo cmj($t['title']);?>)"><i class="fas fa-plus"></i> Add Material</button><button class="cm-btn cm-danger" onclick="deleteRecord('scripts/delete_topic.php',<?php echo $tid;?>,'Delete this topic and all its materials?')"><i class="fas fa-trash"></i></button></div></div>
      <div class="material-list">
<?php foreach($materials as $mat):
    $materialId   = (int)($mat['id'] ?? 0);
    $materialType = (string)($mat['material_type'] ?? 'document');
    $materialDoc  = (string)($mat['document'] ?? '');
    $materialUrl  = (string)($mat['external_url'] ?? '');

    $href = '';
    if ($materialType === 'link') {
        $href = $materialUrl;
    } elseif ($materialDoc !== '') {
        $href = 'course_materials/' . rawurlencode($materialDoc);
    }

    $matExt = strtolower(pathinfo($materialDoc, PATHINFO_EXTENSION));
    $downloadOnlyExt = ['zip','rar','7z','tar','gz','tgz','bz2'];
    $isDownloadOnly = in_array($matExt, $downloadOnlyExt, true);
    $canPreview = $materialId > 0 && !$isDownloadOnly;
?>
    <div class="material-item">
        <div class="mi-title">
            <i class="fas fa-paperclip"></i>
            <?php echo h($mat['title']); ?>
        </div>

        <div class="topic-meta">
            <?php echo h(ucfirst($materialType)); ?>
        </div>

        <div class="cm-actions" style="margin-top:8px">
            <?php if ($isDownloadOnly && $href !== ''): ?>
                <a
                    class="cm-btn cm-soft"
                    href="<?php echo h($href); ?>"
                    download
                    title="Download archive"
                >
                    <i class="fas fa-download"></i> Download
                </a>
            <?php elseif ($canPreview): ?>
    <a
        class="cm-btn cm-soft"
        href="scripts/preview_material.php?id=<?php echo $materialId; ?>"
        target="_blank"
        rel="noopener"
        title="Preview material"
    >
        <i class="fas fa-eye"></i> View
    </a>
<?php endif; ?>

            <a
                class="cm-btn cm-soft"
                href="?view=material_engagement&material_id=<?php echo $materialId; ?>"
                title="View student engagement"
            >
                <i class="fas fa-chart-line"></i> Engagement
            </a>

            <button
                class="cm-btn cm-danger"
                onclick="deleteRecord(
                    'scripts/delete_material.php',
                    <?php echo $materialId; ?>,
                    'Delete this material?'
                )"
                type="button"
                title="Delete material"
            >
                <i class="fas fa-trash"></i>
            </button>
        </div>
    </div>
<?php endforeach; ?>
</div></div>
      <?php endforeach;?>
    </div>
    <div class="cm-panel" id="kind-<?php echo $mi;?>-assignments">
      <?php if(!$assign):?>
        <div class="empty"><p>No assignments yet.</p></div>
      <?php else:?>
        <div class="assignment-grid">
          <?php foreach($assign as $a):?>
            <div class="assignment-card">
              <div class="topic-head">
                <div>
                  <div class="topic-title"><?php echo h($a['title']);?></div>
                  <div class="topic-meta">
                    <?php echo h((string)$a['marks']);?> points .
                    Due <?php echo !empty($a['date_due'])?h(date('d M Y, h:i a',strtotime($a['date_due']))):'-';?> .
                    <?php echo (int)$a['submission_count'];?> submission(s)
                  </div>
                  <div class="topic-meta"><?php echo h($a['short_message']);?></div>
                </div>

                <div class="cm-actions">
                  <a
                    class="cm-btn cm-primary"
                    href="?view=subby&hjadvhagsdjhsagsdhg=<?php echo $mid;?>&piouglv=<?php echo (int)$a['id'];?>"
                  >
                    <i class="fas fa-inbox"></i> Submissions
                  </a>
                </div>
              </div>
            </div>
          <?php endforeach;?>
        </div>
      <?php endif;?>
    </div>
    <div class="cm-panel" id="kind-<?php echo $mi;?>-quizzes">
      <?php if(!$quizzes):?>
        <div class="empty"><p>No quizzes yet.</p></div>
      <?php else:?>
        <div class="quiz-grid">
          <?php foreach($quizzes as $q):?>
            <div class="quiz-card">
              <div class="topic-head">
                <div>
                  <div class="topic-title"><?php echo h($q['title']);?></div>
                  <div class="topic-meta">
                    <?php echo (int)$q['question_count'];?> questions .
                    <?php echo h((string)$q['total_points']);?> points .
                    Pass <?php echo h((string)$q['passing_score']);?>% .
                    Attempts <?php echo (int)$q['max_attempts'];?>
                  </div>
                </div>

                <div class="cm-actions">
                  <a
                    class="cm-btn cm-primary"
                    href="?view=quiz_builder&quiz_id=<?php echo (int)$q['id'];?>"
                  >
                    <i class="fas fa-list-check"></i> Question Builder
                  </a>
                </div>
              </div>
            </div>
          <?php endforeach;?>
        </div>
      <?php endif;?>
    </div>
  </section><?php endforeach;?></div><?php endif;?>
</main>

<div class="modal-overlay" id="createMenu"><div class="modal-box" style="max-width:430px"><div class="modal-head"><strong>Create in <span id="createModuleName"></span></strong><button class="cm-btn cm-soft" onclick="closeModal('createMenu')">X</button></div><div class="modal-body" style="display:grid;gap:10px"><button class="cm-btn cm-primary" onclick="openTopicModal()"><i class="fas fa-list"></i> Topic / Lesson</button><button class="cm-btn cm-gold" onclick="openAssignmentModal()"><i class="fas fa-tasks"></i> Assignment</button><button class="cm-btn cm-soft" onclick="openQuizModal()"><i class="fas fa-question-circle"></i> Quiz</button></div></div></div>
<div class="modal-overlay" id="topicModal"><div class="modal-box"><form id="topicForm"><div class="modal-head"><strong>Create Topic / Lesson</strong><button type="button" class="cm-btn cm-soft" onclick="closeModal('topicModal')">X</button></div><div class="modal-body"><input type="hidden" name="course_id" value="<?php echo $cx;?>"><input type="hidden" name="intake_id" value="<?php echo $intaken;?>"><input type="hidden" name="course_unit_id" class="js-mid"><div class="form-grid"><div class="field span-2"><label>Topic Title *</label><input name="title" required></div><div class="field span-2"><label>Description</label><textarea name="description" rows="4"></textarea></div><div class="field"><label>Order</label><input type="number" name="order_number" min="1" value="1"></div><div class="field"><label>Class Mode</label><select name="class_mode"><option value="both">Both</option><option value="online">Online</option><option value="physical">Physical</option></select></div><div class="field"><label><input type="checkbox" name="is_required" value="1"> Required for completion</label></div></div></div><div class="modal-foot"><button type="button" class="cm-btn cm-soft" onclick="closeModal('topicModal')">Cancel</button><button class="cm-btn cm-primary">Create Topic</button></div></form></div></div>
<div class="modal-overlay" id="materialModal"><div class="modal-box"><form id="materialForm" enctype="multipart/form-data"><div class="modal-head"><strong>Add Material - <span id="materialTopicName"></span></strong><button type="button" class="cm-btn cm-soft" onclick="closeModal('materialModal')">X</button></div><div class="modal-body"><input type="hidden" name="topic_id" id="materialTopicId"><div class="form-grid"><div class="field span-2"><label>Material Title *</label><input name="title" required></div><div class="field"><label>Type *</label><select name="material_type" id="materialType" onchange="toggleMaterialFields()"><option value="document">PDF / Document</option><option value="powerpoint">PowerPoint</option><option value="video">Video</option><option value="audio">Audio</option><option value="image">Image</option><option value="link">External Link</option><option value="text">Text / Reading</option></select></div><div class="field"><label>Order</label><input type="number" name="order_number" value="1" min="1"></div><div class="field span-2"><label>Description</label><textarea name="description" rows="3"></textarea></div><div class="field span-2" id="matFile"><label>File *</label><input type="file" name="document"></div><div class="field span-2" id="matUrl" style="display:none"><label>External URL *</label><input name="external_url"></div><div class="field span-2" id="matText" style="display:none"><label>Reading Content *</label><textarea name="reading_content" rows="8"></textarea></div></div></div><div class="modal-foot"><button type="button" class="cm-btn cm-soft" onclick="closeModal('materialModal')">Cancel</button><button class="cm-btn cm-gold">Add Material</button></div></form></div></div>
<div class="modal-overlay" id="assignmentModal" data-scrollable="true"><div class="modal-box"><form id="assignmentForm" enctype="multipart/form-data"><div class="modal-head"><strong>Create Assignment</strong><button type="button" class="cm-btn cm-soft" onclick="closeModal('assignmentModal')">X</button></div><div class="modal-body"><input type="hidden" name="course_id" value="<?php echo $cx;?>"><input type="hidden" name="intake_id" value="<?php echo $intaken;?>"><input type="hidden" name="course_unit_id" class="js-mid"><div class="form-grid"><div class="field span-2"><label>Assignment Title *</label><input name="title" required></div><div class="field span-2"><label>Instructions *</label><textarea name="instructions" rows="5" required></textarea></div><div class="field"><label>Points *</label><input type="number" step="0.01" min="0" name="marks" required></div><div class="field"><label>Due Date *</label><input type="datetime-local" name="date_due" required></div><div class="field"><label>Submission Type</label><select name="submission_type"><option value="file">File Upload</option><option value="text">Text Response</option><option value="link">Link</option><option value="file_text">File or Text</option><option value="any">Any</option></select></div><div class="field"><label>Maximum Attempts</label><input type="number" name="max_attempts" min="1" value="1"></div><div class="field"><label><input type="checkbox" name="required_for_completion" value="1"> Required for completion</label></div><div class="field"><label><input type="checkbox" name="allow_late_submission" value="1"> Allow late submissions</label></div><div class="field span-2"><label>Resources</label><div id="resourceRows"></div><button type="button" class="cm-btn cm-soft" onclick="addResourceRow()"><i class="fas fa-plus"></i> Add Resource</button></div></div></div><div class="modal-foot"><button type="button" class="cm-btn cm-soft" onclick="closeModal('assignmentModal')">Cancel</button><button class="cm-btn cm-primary">Create Assignment</button></div></form></div></div>
<div class="modal-overlay" id="quizModal"><div class="modal-box"><form id="quizForm"><div class="modal-head"><strong>Create Quiz</strong><button type="button" class="cm-btn cm-soft" onclick="closeModal('quizModal')">X</button></div><div class="modal-body"><input type="hidden" name="course_id" value="<?php echo $cx;?>"><input type="hidden" name="intake_id" value="<?php echo $intaken;?>"><input type="hidden" name="course_unit_id" class="js-mid"><div class="form-grid"><div class="field span-2"><label>Quiz Title *</label><input name="title" required></div><div class="field span-2"><label>Instructions</label><textarea name="instructions" rows="4"></textarea></div><div class="field"><label>Total Points</label><input type="number" step="0.01" name="total_points" value="20"></div><div class="field"><label>Passing Score %</label><input type="number" step="0.01" min="0" max="100" name="passing_score" value="70"></div><div class="field"><label>Attempts</label><input type="number" min="1" name="max_attempts" value="1"></div><div class="field"><label>Deadline</label><input type="datetime-local" name="deadline"></div><div class="field"><label><input type="checkbox" name="required_for_completion" value="1"> Required for completion</label></div></div></div><div class="modal-foot"><button type="button" class="cm-btn cm-soft" onclick="closeModal('quizModal')">Cancel</button><button class="cm-btn cm-primary">Create Quiz</button></div></form></div></div>


</div>

<!-- Custom Message Popup -->
<div class="cm-popup-overlay" id="cmMessagePopup" aria-hidden="true">
  <div class="cm-popup" role="dialog" aria-modal="true" aria-labelledby="cmMessageTitle">
    <div class="cm-popup-head">
      <div class="cm-popup-icon success" id="cmMessageIcon">
        <i class="fas fa-check"></i>
      </div>
      <div class="cm-popup-title" id="cmMessageTitle">Success</div>
    </div>
    <div class="cm-popup-body" id="cmMessageText"></div>
    <div class="cm-popup-foot">
      <button type="button" class="cm-popup-btn primary" id="cmMessageOk">OK</button>
    </div>
  </div>
</div>

<!-- Custom Confirmation Popup -->
<div class="cm-popup-overlay" id="cmConfirmPopup" aria-hidden="true">
  <div class="cm-popup" role="dialog" aria-modal="true" aria-labelledby="cmConfirmTitle">
    <div class="cm-popup-head">
      <div class="cm-popup-icon warning">
        <i class="fas fa-triangle-exclamation"></i>
      </div>
      <div class="cm-popup-title" id="cmConfirmTitle">Please confirm</div>
    </div>
    <div class="cm-popup-body" id="cmConfirmText"></div>
    <div class="cm-popup-foot">
      <button type="button" class="cm-popup-btn soft" id="cmConfirmCancel">Cancel</button>
      <button type="button" class="cm-popup-btn danger" id="cmConfirmYes">Confirm</button>
    </div>
  </div>
</div>

<script>

let currentModuleId=0,currentModuleName='';
function changeIntake(id){const u=new URL(location.href);if(id)u.searchParams.set('ausydfgasuidgipppuwqu',id);else u.searchParams.delete('ausydfgasuidgipppuwqu');location.href=u.toString()}
function showModule(i, b, moduleId) {
    document.querySelectorAll('.dash-tab-panel').forEach(function(panel) {
        panel.classList.remove('active');
    });

    document.querySelectorAll('.dash-tab-btn').forEach(function(btn) {
        btn.classList.remove('active');
        btn.setAttribute('aria-selected', 'false');
    });

    var panel = document.getElementById('cmp-' + i);

    if (panel) {
        panel.classList.add('active');
        panel.scrollIntoView({
            behavior: 'smooth',
            block: 'start'
        });
    }

    if (b) {
        b.classList.add('active');
        b.setAttribute('aria-selected', 'true');
    }

    if (moduleId) {
        try {
            history.replaceState(
                null,
                '',
                window.location.pathname +
                window.location.search.replace(/([?&])module_id=\d+(&?)/, function(match, lead, tail) {
                    return tail ? lead : '';
                }) +
                (window.location.search.includes('?') ? '&' : '?') +
                'module_id=' + encodeURIComponent(moduleId)
            );
        } catch (e) {}
    }
}

function openModuleFromUrl() {
    var params = new URLSearchParams(window.location.search);
    var moduleId = params.get('module_id');

    if (!moduleId) return;

    var btn = document.querySelector(
        '.dash-tab-btn[data-module-id="' + CSS.escape(moduleId) + '"]'
    );

    if (btn) {
        showModule(
            Number(btn.dataset.moduleIndex),
            btn,
            Number(btn.dataset.moduleId)
        );
    }
}

document.addEventListener('DOMContentLoaded', openModuleFromUrl);
function switchKind(i,k,b){b.closest('.dash-tab-panel').querySelectorAll('.cm-panel').forEach(x=>x.classList.remove('active'));b.closest('.cm-tabs').querySelectorAll('.cm-tab').forEach(x=>x.classList.remove('active'));document.getElementById('kind-'+i+'-'+k).classList.add('active');b.classList.add('active')}
function openModal(id){document.getElementById(id).classList.add('is-open');document.body.style.overflow='hidden'}function closeModal(id){document.getElementById(id).classList.remove('is-open');document.body.style.overflow=''}
function openCreateMenu(mid,name){currentModuleId=mid;currentModuleName=name;document.getElementById('createModuleName').textContent=name;document.querySelectorAll('.js-mid').forEach(x=>x.value=mid);openModal('createMenu')}
function openTopicModal(){closeModal('createMenu');openModal('topicModal')}function openAssignmentModal(){closeModal('createMenu');document.getElementById('resourceRows').innerHTML='';openModal('assignmentModal')}function openQuizModal(){closeModal('createMenu');openModal('quizModal')}
function openMaterialModal(id,name){document.getElementById('materialTopicId').value=id;document.getElementById('materialTopicName').textContent=name;document.getElementById('materialForm').reset();document.getElementById('materialTopicId').value=id;toggleMaterialFields();openModal('materialModal')}
function toggleMaterialFields(){const t=document.getElementById('materialType').value;document.getElementById('matFile').style.display=['link','text'].includes(t)?'none':'';document.getElementById('matUrl').style.display=t==='link'?'':'none';document.getElementById('matText').style.display=t==='text'?'':'none'}
let rr=0;function addResourceRow(){const i=rr++;const d=document.createElement('div');d.className='resource-row';d.innerHTML=`<div class="form-grid"><div class="field"><label>Resource title</label><input name="resource_title[]"></div><div class="field"><label>Type</label><select name="resource_type[]" onchange="resourceTypeChanged(this)"><option value="file">File</option><option value="link">Link</option><option value="text">Text</option></select></div><div class="field span-2 r-file"><input type="file" name="resource_file[]"></div><div class="field span-2 r-link" style="display:none"><input name="resource_url[]" placeholder="https://..."></div><div class="field span-2 r-text" style="display:none"><textarea name="resource_text[]" rows="3" placeholder="Resource text"></textarea></div></div>`;document.getElementById('resourceRows').appendChild(d)}
function resourceTypeChanged(s){const r=s.closest('.resource-row'),t=s.value;r.querySelector('.r-file').style.display=t==='file'?'':'none';r.querySelector('.r-link').style.display=t==='link'?'':'none';r.querySelector('.r-text').style.display=t==='text'?'':'none'}
function cmShowMessage(message,type='success',title=''){
    return new Promise(resolve=>{
        const overlay=document.getElementById('cmMessagePopup');
        const icon=document.getElementById('cmMessageIcon');
        const titleEl=document.getElementById('cmMessageTitle');
        const textEl=document.getElementById('cmMessageText');
        const ok=document.getElementById('cmMessageOk');

        const isSuccess=type==='success';
        const isWarning=type==='warning';

        icon.className='cm-popup-icon '+(isSuccess?'success':(isWarning?'warning':'error'));
        icon.innerHTML='<i class="fas '+(isSuccess?'fa-check':(isWarning?'fa-triangle-exclamation':'fa-xmark'))+'"></i>';

        titleEl.textContent=title || (isSuccess?'Success':(isWarning?'Notice':'Error'));
        textEl.textContent=message || '';

        overlay.classList.add('is-open');
        overlay.setAttribute('aria-hidden','false');
        document.body.style.overflow='hidden';

        const close=()=>{
            overlay.classList.remove('is-open');
            overlay.setAttribute('aria-hidden','true');
            document.body.style.overflow='';
            ok.removeEventListener('click',close);
            resolve();
        };

        ok.addEventListener('click',close,{once:true});
    });
}

function cmConfirm(message,title='Please confirm'){
    return new Promise(resolve=>{
        const overlay=document.getElementById('cmConfirmPopup');
        const textEl=document.getElementById('cmConfirmText');
        const titleEl=document.getElementById('cmConfirmTitle');
        const yes=document.getElementById('cmConfirmYes');
        const cancel=document.getElementById('cmConfirmCancel');

        titleEl.textContent=title;
        textEl.textContent=message || '';

        overlay.classList.add('is-open');
        overlay.setAttribute('aria-hidden','false');
        document.body.style.overflow='hidden';

        const finish=(value)=>{
            overlay.classList.remove('is-open');
            overlay.setAttribute('aria-hidden','true');
            document.body.style.overflow='';
            yes.removeEventListener('click',onYes);
            cancel.removeEventListener('click',onCancel);
            resolve(value);
        };

        const onYes=()=>finish(true);
        const onCancel=()=>finish(false);

        yes.addEventListener('click',onYes,{once:true});
        cancel.addEventListener('click',onCancel,{once:true});
    });
}

async function cmReadJsonResponse(response){
    const raw=await response.text();
    try{
        return JSON.parse(raw);
    }catch(e){
        throw new Error(
            raw
                ? 'Server returned an invalid response: '+raw.substring(0,160)
                : 'Server returned an empty response.'
        );
    }
}

async function postForm(form,url){
    const b=form.querySelector('button[type="submit"],button:not([type])');
    const oldHtml=b?b.innerHTML:'';

    if(b){
        b.disabled=true;
        b.innerHTML='<i class="fas fa-spinner fa-spin"></i> Saving...';
    }

    try{
        const r=await fetch(url,{
            method:'POST',
            body:new FormData(form),
            headers:{'X-Requested-With':'XMLHttpRequest'}
        });

        const j=await cmReadJsonResponse(r);

        if(j.success){
            await cmShowMessage(j.message||'Saved successfully.','success');
            location.reload();
        }else{
            await cmShowMessage(j.message||'Request failed.','error');
        }
    }catch(e){
        await cmShowMessage('Request failed: '+e.message,'error');
    }finally{
        if(b){
            b.disabled=false;
            b.innerHTML=oldHtml;
        }
    }
}
document.getElementById('topicForm')?.addEventListener('submit',e=>{e.preventDefault();postForm(e.currentTarget,'scripts/create_topic.php')});document.getElementById('materialForm')?.addEventListener('submit',e=>{e.preventDefault();postForm(e.currentTarget,'scripts/save_topic_material.php')});document.getElementById('assignmentForm')?.addEventListener('submit',e=>{e.preventDefault();postForm(e.currentTarget,'scripts/create_assignment.php')});document.getElementById('quizForm')?.addEventListener('submit',e=>{e.preventDefault();postForm(e.currentTarget,'scripts/create_quiz.php')});
async function deleteRecord(url,id,msg){
    const confirmed=await cmConfirm(msg,'Confirm deletion');
    if(!confirmed)return;

    const f=new FormData();
    f.append('id',id);

    try{
        const r=await fetch(url,{
            method:'POST',
            body:f,
            headers:{'X-Requested-With':'XMLHttpRequest'}
        });

        const j=await cmReadJsonResponse(r);

        if(j.success){
            await cmShowMessage(j.message||'Deleted successfully.','success');
            location.reload();
        }else{
            await cmShowMessage(j.message||'Delete failed.','error');
        }
    }catch(e){
        await cmShowMessage('Delete failed: '+e.message,'error');
    }
}

async function updateModuleAccess(id,mode){
    const f=new FormData();
    f.append('module_id',id);
    f.append('access_mode',mode);

    try{
        const r=await fetch('scripts/update_module_access.php',{
            method:'POST',
            body:f,
            headers:{'X-Requested-With':'XMLHttpRequest'}
        });

        const j=await cmReadJsonResponse(r);

        if(j.success){
            await cmShowMessage(j.message||'Module access updated.','success');
        }else{
            await cmShowMessage(j.message||'Unable to update module access.','error');
        }
    }catch(e){
        await cmShowMessage('Unable to update module access: '+e.message,'error');
    }
}
document.querySelectorAll('.modal-overlay').forEach(m=>m.addEventListener('click',e=>{if(e.target===m)closeModal(m.id)}));

document.getElementById('cmMessagePopup')?.addEventListener('click',function(e){
    if(e.target===this){
        document.getElementById('cmMessageOk')?.click();
    }
});

document.getElementById('cmConfirmPopup')?.addEventListener('click',function(e){
    if(e.target===this){
        document.getElementById('cmConfirmCancel')?.click();
    }
});

document.addEventListener('keydown',function(e){
    if(e.key==='Escape'){
        if(document.getElementById('cmConfirmPopup')?.classList.contains('is-open')){
            document.getElementById('cmConfirmCancel')?.click();
            return;
        }

        if(document.getElementById('cmMessagePopup')?.classList.contains('is-open')){
            document.getElementById('cmMessageOk')?.click();
        }
    }
});


</script>

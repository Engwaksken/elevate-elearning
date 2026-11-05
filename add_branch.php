
<div class="row wrapper border-bottom white-bg page-heading p-3 mb-4">
    <div class="col-lg-10">
        <h2>Add New Branch</h2>
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="?">Home</a></li>
            <li class="breadcrumb-item">Users</li>
            <li class="breadcrumb-item active"><strong>Add New Branch</strong></li>
        </ol>
    </div>
</div>

<div class="wrapper wrapper-content">
    <div class="form-container animated fadeInDown">

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger text-center">
                <?= htmlspecialchars($_SESSION['error_message']); unset($_SESSION['error_message']); ?>
            </div>
        <?php elseif (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success text-center">
                <?= htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?>
            </div>
        <?php endif; ?>

        <div class="container">
    <div class="mx-auto mt-4" style="max-width: 500px;">
        <form id="branchForm" class="border p-4 shadow-sm bg-white">
            <div class="form-group">
                <label for="name">Branch Name <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="name" name="name" required>
            </div>

            <div class="form-group">
                <label for="location">Location</label>
                <input type="text" class="form-control" id="location" name="location">
            </div>

            <button type="submit" class="btn btn-primary btn-block">Submit</button>
            <p></p>

           <p id="message" class="mt-3 text-center font-weight-bold"></p>

        </form>
    </div>
</div>


<script>
    document.getElementById('branchForm').addEventListener('submit', function(e) {
        e.preventDefault();

        const nameField = document.getElementById('name');
        const locationField = document.getElementById('location');
        const name = nameField.value.trim();
        const location = locationField.value.trim();
        const messageElement = document.getElementById('message');

        fetch('scripts/add_branch.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `name=${encodeURIComponent(name)}&location=${encodeURIComponent(location)}`
        })
        .then(response => response.json())
        .then(data => {
            // Set message style and text
            messageElement.className = data.success ? 'text-success' : 'text-danger';
            messageElement.textContent = data.message || 'Unknown response from server.';
            messageElement.style.display = 'block';

            if (data.success) {
                // Clear form fields
                nameField.value = '';
                locationField.value = '';

                // Hide the message after 3 seconds
                setTimeout(() => {
                    messageElement.textContent = '';
                    messageElement.style.display = 'none';
                    messageElement.className = ''; // Reset class
                }, 3000);
            }
        })
        .catch(error => {
            console.error('Fetch error:', error);
            messageElement.className = 'text-danger';
            messageElement.textContent = 'An error occurred. Please try again.';
            messageElement.style.display = 'block';

            // Optional: hide error after 3 seconds
            setTimeout(() => {
                messageElement.textContent = '';
                messageElement.style.display = 'none';
                messageElement.className = '';
            }, 3000);
        });
    });
</script>

</body>
</html>

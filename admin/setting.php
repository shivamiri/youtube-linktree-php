<?php
require_once __DIR__ . '/../common/config.php';

if (!is_admin_logged_in()) {
    redirect('login.php');
}

$msg = '';
$msg_type = '';

// --- HANDLE POST ACTIONS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    // CSRF Verification
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $msg = "Invalid security token. Please refresh and try again.";
        $msg_type = 'error';
    } else {
        $action = $_POST['action'];

        // 1. Update Channel Settings
        if ($action === 'update_channel') {
            $c_name = sanitize($_POST['channel_name']);
            $c_desc = sanitize($_POST['channel_description']);
            $c_url = sanitize($_POST['channel_url']);
            $logo_type = sanitize($_POST['logo_type']);
            $logo_url = sanitize($_POST['logo_url']);
            
            $settings = get_channel_settings();
            $logo_upload = $settings['logo_upload'] ?? '';
            $auto_fetch = isset($_POST['auto_fetch_logo']) ? true : false;
            $extra_msg = "";

            // Manual upload takes priority
            if ($logo_type === 'upload' && isset($_FILES['logo_file']) && $_FILES['logo_file']['error'] === UPLOAD_ERR_OK) {
                $upload_res = upload_image($_FILES['logo_file']);
                if ($upload_res['status']) {
                    $logo_upload = $upload_res['filename'];
                    $auto_fetch = false; // Skip auto-fetch if manual file provided
                } else {
                    $msg = $upload_res['message'];
                    $msg_type = 'error';
                }
            } elseif ($auto_fetch) {
                // Try auto-fetch
                $fetch_res = fetch_channel_logo($c_url);
                if ($fetch_res['status']) {
                    $logo_upload = $fetch_res['filename'];
                    $logo_type = 'upload'; // Save as upload type so it displays from local
                    $extra_msg = "&extra=fetched";
                } else {
                    $msg = $fetch_res['message'];
                    $msg_type = 'error';
                }
            }

            if (empty($msg)) {
                $stmt = $conn->prepare("UPDATE channel_settings SET channel_name=?, channel_description=?, channel_url=?, logo_type=?, logo_upload=?, logo_url=? WHERE id=1");
                $stmt->bind_param("ssssss", $c_name, $c_desc, $c_url, $logo_type, $logo_upload, $logo_url);
                if ($stmt->execute()) {
                    redirect('setting.php?msg=channel_updated' . $extra_msg);
                } else {
                    $msg = "Failed to update settings.";
                    $msg_type = "error";
                }
                $stmt->close();
            }
        }

        // 2. Security Settings
        if ($action === 'update_security') {
            $new_user = sanitize($_POST['username']);
            $old_pass = $_POST['old_password'];
            $new_pass = $_POST['new_password'];

            $admin_id = $_SESSION['admin_id'];
            $stmt = $conn->prepare("SELECT password FROM admin WHERE id = ?");
            $stmt->bind_param("i", $admin_id);
            $stmt->execute();
            $res = $stmt->get_result()->fetch_assoc();
            
            if (password_verify($old_pass, $res['password'])) {
                $hashed = !empty($new_pass) ? password_hash($new_pass, PASSWORD_DEFAULT) : $res['password'];
                $upd = $conn->prepare("UPDATE admin SET username=?, password=? WHERE id=?");
                $upd->bind_param("ssi", $new_user, $hashed, $admin_id);
                if ($upd->execute()) {
                    redirect('setting.php?msg=security_updated');
                }
                $upd->close();
            } else {
                $msg = "Incorrect old password!";
                $msg_type = 'error';
            }
            $stmt->close();
        }
    }
}

// Check GET messages
if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'channel_updated') { 
        $msg = 'Channel settings updated successfully.' . (isset($_GET['extra']) ? ' Logo auto-fetched.' : ''); 
        $msg_type = 'success'; 
    }
    if ($_GET['msg'] === 'security_updated') { $msg = 'Security credentials updated successfully.'; $msg_type = 'success'; }
}

$settings = get_channel_settings();
$csrf_token = generate_csrf_token();

require_once __DIR__ . '/common/header.php';
?>

<?php if (!empty($msg)): ?>
    <div class="mb-6 p-4 rounded-lg <?= $msg_type === 'success' ? 'bg-green-500/20 text-green-400 border border-green-500/30' : 'bg-red-500/20 text-red-400 border border-red-500/30' ?> flex items-center gap-2">
        <i class="fa-solid <?= $msg_type === 'success' ? 'fa-check-circle' : 'fa-triangle-exclamation' ?>"></i>
        <?= htmlspecialchars($msg) ?>
    </div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
    
    <div class="bg-card p-6 rounded-2xl border border-gray-800 shadow-lg">
        <h2 class="text-lg font-bold text-white border-b border-gray-700 pb-4 mb-4 flex items-center gap-2">
            <i class="fa-solid fa-sliders text-primary"></i> Channel Settings
        </h2>
        <!-- Removed static enctype -->
        <form method="POST" action="setting.php" id="channel_form">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
            <input type="hidden" name="action" value="update_channel">
            
            <div class="mb-4">
                <label class="block text-sm text-gray-400 mb-1">Channel Name</label>
                <input type="text" name="channel_name" required value="<?= htmlspecialchars($settings['channel_name'] ?? '') ?>" class="w-full bg-dark border border-gray-700 rounded p-2 text-white outline-none focus:border-primary">
            </div>

            <div class="mb-4">
                <label class="block text-sm text-gray-400 mb-1">Channel Description</label>
                <textarea name="channel_description" rows="3" class="w-full bg-dark border border-gray-700 rounded p-2 text-white outline-none focus:border-primary"><?= htmlspecialchars($settings['channel_description'] ?? '') ?></textarea>
            </div>
            
            <div class="mb-4">
                <label class="block text-sm text-gray-400 mb-1">Main Channel URL</label>
                <input type="url" name="channel_url" required value="<?= htmlspecialchars($settings['channel_url'] ?? '') ?>" class="w-full bg-dark border border-gray-700 rounded p-2 text-white outline-none focus:border-primary">
            </div>

            <div class="mb-4 flex items-center gap-2 bg-dark border border-gray-700 p-3 rounded">
                <input type="checkbox" name="auto_fetch_logo" id="auto_fetch_logo" value="1" class="w-4 h-4 text-primary bg-gray-900 border-gray-700 rounded focus:ring-primary">
                <label for="auto_fetch_logo" class="text-sm text-gray-300">Auto-fetch logo from Channel URL on save</label>
            </div>

            <div class="mb-4">
                <label class="block text-sm text-gray-400 mb-1">Logo Type</label>
                <select name="logo_type" id="logo_type" class="w-full bg-dark border border-gray-700 rounded p-2 text-white outline-none focus:border-primary" onchange="toggleLogoFields()">
                    <option value="url" <?= (isset($settings['logo_type']) && $settings['logo_type'] == 'url') ? 'selected' : '' ?>>Image URL</option>
                    <option value="upload" <?= (isset($settings['logo_type']) && $settings['logo_type'] == 'upload') ? 'selected' : '' ?>>Upload Image / Auto-Fetched</option>
                </select>
            </div>

            <div class="mb-4 hidden" id="logo_upload_div">
                <label class="block text-sm text-gray-400 mb-1">Upload New Logo (Manual)</label>
                <input type="file" name="logo_file" accept="image/*" class="w-full bg-dark border border-gray-700 rounded p-1 text-gray-400 file:mr-4 file:py-1 file:px-4 file:rounded file:border-0 file:text-sm file:bg-primary file:text-white">
                <?php if(isset($settings['logo_type']) && $settings['logo_type'] == 'upload' && !empty($settings['logo_upload'])): ?>
                    <p class="text-xs text-green-400 mt-2">Current Logo is saved locally.</p>
                <?php endif; ?>
            </div>

            <div class="mb-6 hidden" id="logo_url_div">
                <label class="block text-sm text-gray-400 mb-1">Logo URL</label>
                <input type="url" name="logo_url" value="<?= htmlspecialchars($settings['logo_url'] ?? '') ?>" class="w-full bg-dark border border-gray-700 rounded p-2 text-white outline-none focus:border-primary">
            </div>

            <button type="submit" class="w-full bg-primary hover:bg-blue-600 text-white font-medium py-3 rounded-lg transition shadow-lg shadow-blue-500/20">
                Update Settings
            </button>
        </form>
    </div>

    <div class="bg-card p-6 rounded-2xl border border-gray-800 shadow-lg h-fit">
        <h2 class="text-lg font-bold text-white border-b border-gray-700 pb-4 mb-4 flex items-center gap-2">
            <i class="fa-solid fa-lock text-red-500"></i> Admin Security
        </h2>
        <?php 
            $admin_id = $_SESSION['admin_id'];
            $curr_user = $conn->query("SELECT username FROM admin WHERE id = $admin_id")->fetch_assoc()['username'];
        ?>
        <form method="POST" action="setting.php">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
            <input type="hidden" name="action" value="update_security">
            
            <div class="mb-4">
                <label class="block text-sm text-gray-400 mb-1">Admin Username</label>
                <input type="text" name="username" required value="<?= htmlspecialchars($curr_user) ?>" class="w-full bg-dark border border-gray-700 rounded p-2 text-white outline-none focus:border-primary">
            </div>
            
            <div class="mb-4">
                <label class="block text-sm text-gray-400 mb-1">New Password <span class="text-xs text-gray-500">(leave blank to keep current)</span></label>
                <input type="password" name="new_password" class="w-full bg-dark border border-gray-700 rounded p-2 text-white outline-none focus:border-primary">
            </div>
            
            <div class="mb-6 p-4 bg-red-500/5 rounded-lg border border-red-500/20">
                <label class="block text-sm text-red-400 mb-1">Current Password (Required) *</label>
                <input type="password" name="old_password" required placeholder="Enter current password to save changes" class="w-full bg-dark border border-gray-700 rounded p-2 text-white outline-none focus:border-red-500">
            </div>
            
            <button type="submit" class="w-full bg-red-500/10 hover:bg-red-500/20 text-red-500 border border-red-500/20 font-medium py-3 rounded-lg transition">
                Update Credentials
            </button>
        </form>
    </div>

</div>

<script>
    function toggleLogoFields() {
        var type = document.getElementById('logo_type').value;
        var form = document.getElementById('channel_form');
        
        document.getElementById('logo_upload_div').style.display = (type === 'upload') ? 'block' : 'none';
        document.getElementById('logo_url_div').style.display = (type === 'url') ? 'block' : 'none';
        
        if (type === 'upload') {
            form.setAttribute('enctype', 'multipart/form-data');
        } else {
            form.removeAttribute('enctype');
        }
    }
    window.onload = function() {
        toggleLogoFields();
    };
</script>

<?php require_once __DIR__ . '/common/bottom.php'; ?>

<?php
require_once __DIR__ . '/../common/config.php';

if (!is_admin_logged_in()) {
    redirect('login.php');
}

$msg = '';
$msg_type = '';

// Helper function for YouTube Title Auto-Fetch (Updated for both HTTP/HTTPS & Local Support)
function fetch_youtube_title($video_id) {
    // Try HTTPS first
    $urls = [
        "https://www.youtube.com/oembed?url=https://www.youtube.com/watch?v=" . $video_id . "&format=json",
        "https://www.youtube.com/oembed?url=http://www.youtube.com/watch?v=" . $video_id . "&format=json"
    ];
    
    foreach ($urls as $url) {
        $response = '';
        
        // 1. Try cURL (with SSL verification disabled for local server compatibility)
        if (function_exists('curl_version')) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 8);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            $response = @curl_exec($ch);
            curl_close($ch);
        }
        
        // 2. Fallback to file_get_contents
        if (empty($response) && ini_get('allow_url_fopen')) {
            $context = stream_context_create([
                'http' => ['ignore_errors' => true],
                'ssl'  => ['verify_peer' => false, 'verify_peer_name' => false]
            ]);
            $response = @file_get_contents($url, false, $context);
        }

        if ($response) {
            $data = json_decode($response, true);
            if (isset($data['title'])) {
                return $data['title'];
            }
        }
    }
    return false;
}

// --- HANDLE POST ACTIONS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    // CSRF Verification for all POST actions
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $msg = "Invalid security token. Please refresh and try again.";
        $msg_type = 'error';
    } else {
        // Delete Video (Converted from GET to POST for security)
        if ($_POST['action'] === 'delete_video') {
            $del_id = intval($_POST['delete_id']);
            $stmt = $conn->prepare("DELETE FROM videos WHERE id = ?");
            $stmt->bind_param("i", $del_id);
            if ($stmt->execute()) {
                redirect('index.php?msg=deleted');
            }
            $stmt->close();
        }

        // Add / Edit Video
        if ($_POST['action'] === 'save_video') {
            $video_id_pk = isset($_POST['video_id']) ? intval($_POST['video_id']) : 0;
            $yt_url = sanitize($_POST['youtube_url']);
            $title = sanitize($_POST['title']);
            $thumb_type = sanitize($_POST['thumbnail_type']);
            $thumb_url = sanitize($_POST['thumbnail_url']);
            $display_order = intval($_POST['display_order']);
            $status = sanitize($_POST['status']);
            
            $yt_id = get_youtube_id($yt_url);
            if (!$yt_id) {
                $msg = "Invalid YouTube URL.";
                $msg_type = 'error';
            } else {
                // Auto Title
                if (empty($title)) {
                    $auto_title = fetch_youtube_title($yt_id);
                    $title = $auto_title ? $auto_title : 'Untitled Video';
                }

                // Thumbnail Upload Handling
                $thumb_upload = '';
                if ($video_id_pk > 0) {
                    $st = $conn->query("SELECT thumbnail_upload FROM videos WHERE id = $video_id_pk");
                    if ($row = $st->fetch_assoc()) $thumb_upload = $row['thumbnail_upload'];
                }

                if ($thumb_type === 'upload' && isset($_FILES['thumbnail_file']) && $_FILES['thumbnail_file']['error'] === UPLOAD_ERR_OK) {
                    $upload_res = upload_image($_FILES['thumbnail_file']);
                    if ($upload_res['status']) {
                        $thumb_upload = $upload_res['filename'];
                    } else {
                        $msg = $upload_res['message'];
                        $msg_type = 'error';
                    }
                }

                if (empty($msg)) {
                    if ($video_id_pk > 0) {
                        $stmt = $conn->prepare("UPDATE videos SET youtube_url=?, youtube_video_id=?, title=?, thumbnail_type=?, thumbnail_upload=?, thumbnail_url=?, display_order=?, status=? WHERE id=?");
                        $stmt->bind_param("ssssssisi", $yt_url, $yt_id, $title, $thumb_type, $thumb_upload, $thumb_url, $display_order, $status, $video_id_pk);
                    } else {
                        $stmt = $conn->prepare("INSERT INTO videos (youtube_url, youtube_video_id, title, thumbnail_type, thumbnail_upload, thumbnail_url, display_order, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt->bind_param("ssssssis", $yt_url, $yt_id, $title, $thumb_type, $thumb_upload, $thumb_url, $display_order, $status);
                    }
                    if ($stmt->execute()) {
                        redirect('index.php?msg=video_saved');
                    } else {
                        $msg = "Database error while saving video.";
                        $msg_type = 'error';
                    }
                    $stmt->close();
                }
            }
        }
    }
}

// Check GET messages
if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'deleted') { $msg = 'Video deleted successfully.'; $msg_type = 'success'; }
    if ($_GET['msg'] === 'video_saved') { $msg = 'Video saved successfully.'; $msg_type = 'success'; }
}

// Fetch Data for Dashboard Summary
$total_videos = $conn->query("SELECT COUNT(*) as c FROM videos")->fetch_assoc()['c'];
$active_videos = $conn->query("SELECT COUNT(*) as c FROM videos WHERE status='active'")->fetch_assoc()['c'];
$last_video = $conn->query("SELECT title FROM videos ORDER BY id DESC LIMIT 1")->fetch_assoc()['title'] ?? 'N/A';

$settings = get_channel_settings();

// Check if Editing a video
$edit_video = null;
if (isset($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $st = $conn->prepare("SELECT * FROM videos WHERE id = ?");
    $st->bind_param("i", $edit_id);
    $st->execute();
    $edit_video = $st->get_result()->fetch_assoc();
    $st->close();
}

$csrf_token = generate_csrf_token();

require_once __DIR__ . '/common/header.php';
?>

<?php if (!empty($msg)): ?>
    <div class="mb-6 p-4 rounded-lg <?= $msg_type === 'success' ? 'bg-green-500/20 text-green-400 border border-green-500/30' : 'bg-red-500/20 text-red-400 border border-red-500/30' ?> flex items-center gap-2">
        <i class="fa-solid <?= $msg_type === 'success' ? 'fa-check-circle' : 'fa-triangle-exclamation' ?>"></i>
        <?= htmlspecialchars($msg) ?>
    </div>
<?php endif; ?>

<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
    <div class="bg-card p-6 rounded-2xl border border-gray-800 flex items-center shadow-lg">
        <div class="w-12 h-12 rounded-full bg-blue-500/10 text-blue-500 flex items-center justify-center text-xl mr-4">
            <i class="fa-solid fa-video"></i>
        </div>
        <div>
            <p class="text-gray-400 text-sm">Total Videos</p>
            <h3 class="text-2xl font-bold text-white"><?= $total_videos ?> <span class="text-sm text-gray-500 font-normal">(<?= $active_videos ?> active)</span></h3>
        </div>
    </div>
    <div class="bg-card p-6 rounded-2xl border border-gray-800 flex items-center shadow-lg">
        <div class="w-12 h-12 rounded-full bg-purple-500/10 text-purple-500 flex items-center justify-center text-xl mr-4">
            <i class="fa-brands fa-youtube"></i>
        </div>
        <div class="overflow-hidden">
            <p class="text-gray-400 text-sm">Channel Name</p>
            <h3 class="text-xl font-bold text-white truncate"><?= htmlspecialchars($settings['channel_name'] ?? 'Not Set') ?></h3>
        </div>
    </div>
    <div class="bg-card p-6 rounded-2xl border border-gray-800 flex items-center shadow-lg">
        <div class="w-12 h-12 rounded-full bg-green-500/10 text-green-500 flex items-center justify-center text-xl mr-4">
            <i class="fa-solid fa-clock-rotate-left"></i>
        </div>
        <div class="overflow-hidden">
            <p class="text-gray-400 text-sm">Last Added</p>
            <h3 class="text-sm font-bold text-white truncate" title="<?= htmlspecialchars($last_video) ?>"><?= htmlspecialchars($last_video) ?></h3>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    
    <div class="lg:col-span-1 space-y-8">
        <div class="bg-card p-6 rounded-2xl border border-gray-800 shadow-lg" id="video-form">
            <div class="flex justify-between items-center border-b border-gray-700 pb-4 mb-4">
                <h2 class="text-lg font-bold text-white flex items-center gap-2">
                    <i class="fa-solid <?= $edit_video ? 'fa-pen-to-square' : 'fa-plus' ?> text-primary"></i> 
                    <?= $edit_video ? 'Edit Video' : 'Add New Video' ?>
                </h2>
                <?php if ($edit_video): ?>
                    <a href="index.php" class="text-sm text-gray-400 hover:text-white bg-gray-800 px-2 py-1 rounded">Cancel</a>
                <?php endif; ?>
            </div>
            
            <form method="POST" action="index.php" id="video_submit_form">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                <input type="hidden" name="action" value="save_video">
                <?php if ($edit_video): ?>
                    <input type="hidden" name="video_id" value="<?= $edit_video['id'] ?>">
                <?php endif; ?>

                <div class="mb-4">
                    <label class="block text-sm text-gray-400 mb-1">YouTube Video URL *</label>
                    <input type="url" name="youtube_url" required value="<?= $edit_video ? htmlspecialchars($edit_video['youtube_url']) : '' ?>" class="w-full bg-dark border border-gray-700 rounded p-2 text-white outline-none focus:border-primary">
                </div>

                <div class="mb-4">
                    <label class="block text-sm text-gray-400 mb-1">Video Title <span class="text-xs text-gray-500">(Leave blank for auto-fetch)</span></label>
                    <input type="text" name="title" value="<?= $edit_video ? htmlspecialchars($edit_video['title']) : '' ?>" class="w-full bg-dark border border-gray-700 rounded p-2 text-white outline-none focus:border-primary">
                </div>

                <div class="mb-4">
                    <label class="block text-sm text-gray-400 mb-1">Thumbnail Option</label>
                    <select name="thumbnail_type" id="thumb_type" class="w-full bg-dark border border-gray-700 rounded p-2 text-white outline-none focus:border-primary" onchange="toggleThumbFields()">
                        <option value="auto" <?= ($edit_video && $edit_video['thumbnail_type'] == 'auto') ? 'selected' : '' ?>>Auto (From YouTube)</option>
                        <option value="upload" <?= ($edit_video && $edit_video['thumbnail_type'] == 'upload') ? 'selected' : '' ?>>Upload Image</option>
                        <option value="url" <?= ($edit_video && $edit_video['thumbnail_type'] == 'url') ? 'selected' : '' ?>>Image URL</option>
                    </select>
                </div>

                <div class="mb-4 hidden" id="thumb_upload_div">
                    <label class="block text-sm text-gray-400 mb-1">Upload Thumbnail Image</label>
                    <input type="file" name="thumbnail_file" accept="image/*" class="w-full bg-dark border border-gray-700 rounded p-1 text-gray-400 file:mr-4 file:py-1 file:px-4 file:rounded file:border-0 file:text-sm file:bg-primary file:text-white hover:file:bg-blue-600">
                </div>

                <div class="mb-4 hidden" id="thumb_url_div">
                    <label class="block text-sm text-gray-400 mb-1">Thumbnail URL</label>
                    <input type="url" name="thumbnail_url" value="<?= $edit_video ? htmlspecialchars($edit_video['thumbnail_url']) : '' ?>" class="w-full bg-dark border border-gray-700 rounded p-2 text-white outline-none focus:border-primary">
                </div>

                <div class="grid grid-cols-2 gap-4 mb-6">
                    <div>
                        <label class="block text-sm text-gray-400 mb-1">Display Order</label>
                        <input type="number" name="display_order" value="<?= $edit_video ? $edit_video['display_order'] : '0' ?>" class="w-full bg-dark border border-gray-700 rounded p-2 text-white outline-none focus:border-primary">
                    </div>
                    <div>
                        <label class="block text-sm text-gray-400 mb-1">Status</label>
                        <select name="status" class="w-full bg-dark border border-gray-700 rounded p-2 text-white outline-none focus:border-primary">
                            <option value="active" <?= ($edit_video && $edit_video['status'] == 'active') ? 'selected' : '' ?>>Active</option>
                            <option value="hidden" <?= ($edit_video && $edit_video['status'] == 'hidden') ? 'selected' : '' ?>>Hidden</option>
                        </select>
                    </div>
                </div>

                <button type="submit" class="w-full bg-primary hover:bg-blue-600 text-white font-medium py-3 rounded-lg transition shadow-lg shadow-blue-500/20">
                    <?= $edit_video ? 'Update Video' : 'Save Video' ?>
                </button>
            </form>
        </div>
    </div>

    <div class="lg:col-span-2">
        <div class="bg-card p-6 rounded-2xl border border-gray-800 shadow-lg h-full">
            <h2 class="text-lg font-bold text-white border-b border-gray-700 pb-4 mb-4 flex items-center gap-2">
                <i class="fa-solid fa-list text-primary"></i> Manage Videos
            </h2>
            
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-gray-400">
                    <thead class="text-xs uppercase bg-dark text-gray-400">
                        <tr>
                            <th class="px-4 py-3 rounded-tl-lg">Video</th>
                            <th class="px-4 py-3">Order</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3 rounded-tr-lg text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $videos = $conn->query("SELECT * FROM videos ORDER BY display_order ASC, created_at DESC");
                        if ($videos && $videos->num_rows > 0):
                            while ($v = $videos->fetch_assoc()):
                        ?>
                        <tr class="border-b border-gray-800 hover:bg-dark transition-colors">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <div class="w-16 h-10 bg-gray-900 rounded overflow-hidden flex-shrink-0 relative">
                                        <?php 
                                        $t_src = '';
                                        if($v['thumbnail_type'] == 'upload' && !empty($v['thumbnail_upload'])) $t_src = '../upload/'.$v['thumbnail_upload'];
                                        elseif($v['thumbnail_type'] == 'url') $t_src = $v['thumbnail_url'];
                                        else $t_src = "https://img.youtube.com/vi/".$v['youtube_video_id']."/hqdefault.jpg";
                                        ?>
                                        <img src="<?= htmlspecialchars($t_src) ?>" alt="thumb" class="w-full h-full object-cover">
                                    </div>
                                    <div class="truncate max-w-[150px] sm:max-w-xs text-white font-medium">
                                        <?= htmlspecialchars($v['title']) ?>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3"><?= $v['display_order'] ?></td>
                            <td class="px-4 py-3">
                                <?php if($v['status'] == 'active'): ?>
                                    <span class="bg-green-500/10 text-green-500 text-xs px-2 py-1 rounded">Active</span>
                                <?php else: ?>
                                    <span class="bg-gray-700 text-gray-300 text-xs px-2 py-1 rounded">Hidden</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <a href="index.php?edit=<?= $v['id'] ?>#video-form" class="text-blue-400 hover:text-blue-300 px-2 py-1 inline-block transition">
                                    <i class="fa-solid fa-pen"></i>
                                </a>
                                <form method="POST" action="index.php" class="inline-block m-0 p-0" onsubmit="return confirm('Are you sure you want to delete this video?');">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                    <input type="hidden" name="action" value="delete_video">
                                    <input type="hidden" name="delete_id" value="<?= $v['id'] ?>">
                                    <button type="submit" class="text-red-400 hover:text-red-300 px-2 py-1 inline-block transition bg-transparent border-none cursor-pointer">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endwhile; else: ?>
                        <tr>
                            <td colspan="4" class="px-4 py-8 text-center text-gray-500">No videos found. Add one from the form!</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
    function toggleThumbFields() {
        var type = document.getElementById('thumb_type').value;
        var form = document.getElementById('video_submit_form');
        
        document.getElementById('thumb_upload_div').style.display = (type === 'upload') ? 'block' : 'none';
        document.getElementById('thumb_url_div').style.display = (type === 'url') ? 'block' : 'none';
        
        if (type === 'upload') {
            form.setAttribute('enctype', 'multipart/form-data');
        } else {
            form.removeAttribute('enctype');
        }
    }

    window.onload = function() {
        toggleThumbFields();
    };
</script>

<?php require_once __DIR__ . '/common/bottom.php'; ?>

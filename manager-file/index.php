<?php
require __DIR__ . '/config.php';

// Wajib login
if (!isset($_SESSION['user_id'])) {
    header('Location: auth.php');
    exit;
}

$userId      = (int)$_SESSION['user_id'];
$userName    = $_SESSION['user_name']  ?? 'User';
$userEmail   = $_SESSION['user_email'] ?? '';
$userAvatar  = $_SESSION['user_avatar'] ?? null;   // nama file avatar di DB
$initial     = strtoupper(mb_substr($userName, 0, 1, 'UTF-8'));
$isAdmin     = !empty($_SESSION['user_is_admin']);
$avatarUrl   = $userAvatar ? 'avatars/' . $userAvatar : null;

// info storage
$stmt = $pdo->prepare("SELECT storage_used, storage_limit FROM users WHERE id = ?");
$stmt->execute([$userId]);
$u = $stmt->fetch();
$storageUsed  = (int)($u['storage_used']  ?? 0);
$storageLimit = (int)($u['storage_limit'] ?? (1024*1024*1024)); // default 1GB

$isUnlimitedPlan = $storageLimit >= 900 * 1024 * 1024 * 1024; // >= 900GB dianggap Unlimited
function fmSizeMB($bytes) { return round($bytes / 1024 / 1024, 2); }
$usedMB  = fmSizeMB($storageUsed);
$limitMB = fmSizeMB($storageLimit);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Modern File Manager (PHP)</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
:root {
  --bg: #0f172a;
  --bg-soft: #111827;
  --accent: #3b82f6;
  --accent-soft: rgba(59,130,246,0.15);
  --text: #e5e7eb;
  --text-soft: #9ca3af;
  --border: rgba(148,163,184,0.25);
  --danger: #ef4444;
  --radius-lg: 14px;
  --shadow-soft: 0 18px 40px rgba(15,23,42,0.9);
}

* { box-sizing: border-box; margin:0; padding:0; }
body {
  font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
  background: radial-gradient(circle at top, #1f2937 0, #020617 50%, #000 100%);
  min-height: 100vh;
  margin:0;
  padding:10px;
  color: var(--text);
}

/* kontainer utama sekarang full width, tapi tetap ada max-width di layar besar */
.app {
  width:100%;
  max-width:1400px;
  margin:0 auto;
  background: linear-gradient(135deg, var(--bg-soft), #020617);
  border-radius: 22px;
  box-shadow: var(--shadow-soft);
  border:1px solid var(--border);
  padding:16px 18px 18px;
}

/* HEADER + USER MENU */

.header {
  display:flex;
  justify-content:space-between;
  align-items:center;
  margin-bottom:12px;
  gap:12px;
}

.title-group {
  display:flex;
  flex-direction:column;
  gap:4px;
}

.title {
  font-size:18px;
  font-weight:600;
}

.subtitle {
  font-size:12px;
  color:var(--text-soft);
}

.header-right {
  display:flex;
  align-items:center;
  gap:10px;
}

.badge {
  font-size:11px;
  padding:3px 8px;
  border-radius:999px;
  border:1px solid var(--border);
  color:var(--text-soft);
}

/* USER MENU */

.user-menu {
  position:relative;
}

.user-avatar-btn {
  display:flex;
  align-items:center;
  gap:8px;
  padding:4px 6px;
  border-radius:999px;
  border:1px solid var(--border);
  background:rgba(15,23,42,0.9);
  cursor:pointer;
  transition:background 0.15s ease-out, transform 0.15s ease-out;
}

.user-avatar-btn:hover {
  background:rgba(15,23,42,1);
  transform:translateY(-1px);
}

.user-avatar-circle {
  width:30px;
  height:30px;
  border-radius:999px;
  background:linear-gradient(135deg,#22c55e,#16a34a);
  display:flex;
  align-items:center;
  justify-content:center;
  font-size:14px;
  font-weight:600;
  color:#fff;
}

.user-name-short {
  font-size:13px;
  color:var(--text-soft);
  max-width:120px;
  white-space:nowrap;
  overflow:hidden;
  text-overflow:ellipsis;
}

.user-menu-dropdown {
  position:absolute;
  right:0;
  top:40px;
  min-width:210px;
  background:rgba(15,23,42,0.98);
  border-radius:14px;
  border:1px solid var(--border);
  box-shadow:0 18px 40px rgba(0,0,0,0.8);
  padding:8px 0;
  z-index:20;
  display:none;
}

.user-menu-dropdown.open {
  display:block;
}

.user-menu-header {
  padding:8px 12px;
  border-bottom:1px solid rgba(31,41,55,0.9);
}

.user-menu-header-name {
  font-size:13px;
  font-weight:500;
}
.user-menu-header-email {
  font-size:11px;
  color:var(--text-soft);
}

.user-menu-item {
  padding:7px 12px;
  font-size:13px;
  display:flex;
  align-items:center;
  gap:8px;
  cursor:pointer;
  color:var(--text-soft);
  text-decoration:none;
}
.user-menu-item:hover {
  background:rgba(15,23,42,1);
  color:var(--text);
}
.user-menu-item.danger {
  color:var(--danger);
}
.user-menu-item.danger:hover {
  background:rgba(127,29,29,0.9);
  color:#fee2e2;
}

/* TOOLBAR & MAIN */

.toolbar {
  display:flex;
  flex-wrap:wrap;
  gap:8px;
  margin-bottom:10px;
}

.btn-primary {
  border:none;
  border-radius:999px;
  padding:7px 14px;
  font-size:13px;
  display:inline-flex;
  align-items:center;
  gap:6px;
  background:linear-gradient(135deg,#3b82f6,#2563eb);
  color:white;
  cursor:pointer;
  box-shadow:0 10px 22px rgba(37,99,235,0.8);
}
.btn-primary:hover { transform:translateY(-1px); }

.btn-soft {
  border-radius:999px;
  padding:7px 12px;
  font-size:12px;
  border:1px solid var(--border);
  background:rgba(15,23,42,0.8);
  color:var(--text-soft);
  display:inline-flex;
  align-items:center;
  gap:6px;
  cursor:pointer;
}
.btn-soft:hover { background:rgba(15,23,42,1); color:var(--text); }

.btn-soft.active-tab {
  background:var(--accent-soft);
  color:#fff;
  border-color:var(--accent);
}

.input-search {
  flex:1;
  min-width:180px;
  border-radius:999px;
  border:1px solid var(--border);
  background:rgba(15,23,42,0.8);
  padding:6px 12px;
  display:flex;
  align-items:center;
  gap:6px;
}
.input-search input {
  background:transparent;
  border:none;
  outline:none;
  color:var(--text);
  font-size:13px;
  width:100%;
}
.input-search span { font-size:14px; }

.main-card {
  border-radius:var(--radius-lg);
  background:rgba(15,23,42,0.9);
  border:1px solid var(--border);
  padding:10px;
}

.dropzone {
  border-radius:var(--radius-lg);
  border:1px dashed rgba(148,163,184,0.6);
  padding:12px 10px;
  background:repeating-linear-gradient(-45deg, rgba(15,23,42,0.85), rgba(15,23,42,0.85) 4px, rgba(15,23,42,0.95) 4px, rgba(15,23,42,0.95) 8px);
  display:flex;
  justify-content:space-between;
  align-items:center;
  cursor:pointer;
  margin-bottom:10px;
}
.dropzone-left {
  display:flex;
  gap:10px;
  align-items:center;
}
.dropzone-icon {
  width:34px; height:34px;
  border-radius:14px;
  background:rgba(37,99,235,0.2);
  display:flex;align-items:center;justify-content:center;
  font-size:18px;color:var(--accent);
}
.dropzone-title { font-size:13px; font-weight:500;}
.dropzone-sub { font-size:11px; color:var(--text-soft);}
.dropzone.dragover {
  border-color:var(--accent);
  background:radial-gradient(circle at top, rgba(59,130,246,0.28), var(--bg));
}

.table-wrapper {
  border-radius:var(--radius-lg);
  border:1px solid var(--border);
  background:rgba(15,23,42,0.95);
  overflow:hidden;
  max-height: calc(100vh - 240px);
  display:flex;
  flex-direction:column;
}
@media (max-width: 768px) {
  body {
    padding:0;
  }
  .app {
    border-radius:0;
    box-shadow:none;
    border:none;
    max-width:100%;
    padding:10px;
  }
}
.table-scroll {
  overflow:auto;
}

.table-header {
  display:flex;
  justify-content:space-between;
  align-items:center;
  font-size:11px;
  color:var(--text-soft);
  padding:7px 10px;
  border-bottom:1px solid var(--border);
}

.table-header-left {
  display:flex;
  align-items:center;
  gap:6px;
}

/* tombol kembali di atas list */
.back-button {
  padding:5px 12px;
  border-radius:999px;
  background:rgba(15,23,42,0.9);
  border:1px solid var(--border);
  color:var(--text-soft);
  font-size:12px;
  cursor:pointer;
}
.back-button:hover {
  background:rgba(15,23,42,1);
  color:var(--text);
}

/* breadcrumb */
.breadcrumb {
  font-size:11px;
  color:var(--text-soft);
  margin-left:4px;
  display:flex;
  align-items:center;
  gap:4px;
  flex-wrap:wrap;
}
.breadcrumb span {
  cursor:pointer;
}
.breadcrumb span:hover {
  text-decoration:underline;
  color:var(--text);
}

.table-header-right {
  display:flex;
  gap:8px;
  align-items:center;
}

table {
  width:100%;
  border-collapse:collapse;
  font-size:13px;
}
thead {
  background:rgba(15,23,42,1);
  position:sticky;
  top:0;
}
th, td {
  padding:7px 10px;
  border-bottom:1px solid rgba(31,41,55,0.9);
  text-align:left;
  white-space:nowrap;
}
th {
  font-size:11px;
  text-transform:uppercase;
  letter-spacing:0.08em;
  color:var(--text-soft);
}
tbody tr:hover {
  background:rgba(15,23,42,1);
}

.file-name-cell {
  display:flex;
  gap:8px;
  align-items:center;
}
.file-icon {
  width:24px;height:24px;
  border-radius:10px;
  background:rgba(37,99,235,0.3);
  display:flex;align-items:center;justify-content:center;
  font-size:12px;color:white;
}
.file-meta {
  font-size:11px;
  color:var(--text-soft);
}

/* NAMA FILE/FOLDER BISA DIKLIK */
.file-name-link {
  background:none;
  border:none;
  padding:0;
  margin:0;
  font:inherit;
  color:inherit;
  cursor:pointer;
  text-align:left;
}
.file-name-link:hover {
  text-decoration:underline;
}

.actions {
  display:flex;
  gap:6px;
  justify-content:flex-end;
}
.actions button {
  border-radius:999px;
  border:1px solid rgba(71,85,105,0.9);
  background:rgba(15,23,42,1);
  font-size:11px;
  padding:3px 7px;
  cursor:pointer;
  color:var(--text-soft);
  display:inline-flex;
  align-items:center;
  gap:4px;
}
.actions button:hover { color:var(--text); }
.actions .danger {
  border-color:rgba(248,113,113,0.9);
  color:var(--danger);
}
.actions .danger:hover {
  background:rgba(127,29,29,0.95);
  color:#fff;
}

.empty {
  padding:20px;
  text-align:center;
  color:var(--text-soft);
  font-size:13px;
}

/* ===== MODAL UPLOAD ===== */
.upload-modal-overlay {
  position: fixed;
  inset: 0;
  display: none;
  align-items: center;
  justify-content: center;
  background: rgba(15,23,42,0.75);
  backdrop-filter: blur(6px);
  z-index: 60;
}

.upload-modal {
  width: 420px;
  max-width: 90vw;
  border-radius: 20px;
  background: radial-gradient(circle at top, rgba(59,130,246,0.18), #020617);
  border: 1px solid var(--border);
  box-shadow: 0 22px 50px rgba(0,0,0,0.85);
  padding: 14px 16px 14px;
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.upload-modal-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 10px;
}

.upload-modal-title {
  font-size: 15px;
  font-weight: 600;
}

.upload-modal-sub {
  font-size: 11px;
  color: var(--text-soft);
}

.upload-modal-close {
  border: none;
  background: transparent;
  color: var(--text-soft);
  font-size: 16px;
  cursor: pointer;
}
.upload-modal-close:hover {
  color: var(--text);
}

.upload-modal-dropzone {
  margin-top: 4px;
  padding: 14px 12px;
  border-radius: 16px;
  border: 1px dashed rgba(148,163,184,0.8);
  background: rgba(15,23,42,0.95);
  display: flex;
  align-items: center;
  gap: 10px;
  cursor: pointer;
}
.upload-modal-dropzone:hover {
  border-color: var(--accent);
}
.upload-modal-dropzone.dragover {
  border-color: var(--accent);
  background: radial-gradient(circle at top, rgba(59,130,246,0.22), #020617);
}

.upload-modal-drop-icon {
  width: 36px;
  height: 36px;
  border-radius: 14px;
  background: rgba(37,99,235,0.25);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 20px;
}

.upload-modal-drop-title {
  font-size: 13px;
  font-weight: 500;
}
.upload-modal-drop-sub {
  font-size: 11px;
  color: var(--text-soft);
}

.upload-modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 8px;
  margin-top: 4px;
}

/* UPLOAD PROGRESS (dipakai di dalam modal) */
.upload-progress-wrapper {
  margin-top: 6px;
  padding: 7px 9px;
  border-radius: 12px;
  background: rgba(15,23,42,0.9);
  border: 1px solid var(--border);
}

.upload-progress-text {
  font-size: 11px;
  color: var(--text-soft);
  margin-bottom: 4px;
}

.upload-progress-bar {
  width: 100%;
  height: 6px;
  border-radius: 999px;
  background: rgba(30,64,175,0.4);
  overflow: hidden;
}

.upload-progress-bar-inner {
  height: 100%;
  width: 0%;
  border-radius: 999px;
  background: linear-gradient(90deg,#3b82f6,#22c55e);
  transition: width 0.2s ease-out;
}

@media (max-width:720px){
  .header { flex-direction:column; align-items:flex-start; gap:8px; }
  .header-right { align-self:flex-end; }
  .toolbar { flex-direction:column; align-items:stretch; }
}
.user-avatar-circle img{
  width:100%;
  height:100%;
  border-radius:999px;
  object-fit:cover;
  display:block;
}
</style>
</head>
<body>
<div class="app">
  <div class="header">
    <div class="title-group">
      <div class="title">File Manager</div>
      <div class="subtitle">
        Semua file disimpan di folder <b>data/user_<?php echo $userId; ?>/</b>.
        Penyimpanan:
        <b><?php echo $usedMB; ?> MB / <?php echo $isUnlimitedPlan ? 'Unlimited' : ($limitMB . ' MB'); ?></b>
      </div>
    </div>

    <div class="header-right">
      <div class="badge" id="badgeInfo">0 item</div>

      <div class="user-menu" id="userMenu">
        <div class="user-avatar-btn" id="userAvatarBtn">
          <div class="user-avatar-circle">
            <?php if ($avatarUrl): ?>
              <img src="<?php echo htmlspecialchars($avatarUrl); ?>" alt="Avatar">
            <?php else: ?>
              <?php echo htmlspecialchars($initial); ?>
            <?php endif; ?>
          </div>
          <div class="user-name-short"><?php echo htmlspecialchars($userName); ?></div>
        </div>

        <div class="user-menu-dropdown" id="userDropdown">
          <div class="user-menu-header">
            <div class="user-menu-header-name">
              <?php echo htmlspecialchars($userName); ?>
            </div>
            <div class="user-menu-header-email">
              <?php echo htmlspecialchars($userEmail); ?>
            </div>
          </div>

          <?php if ($isAdmin): ?>
            <a class="user-menu-item" href="admin_users.php">
              <span>🛠</span><span>Admin Panel</span>
            </a>
          <?php endif; ?>

          <a class="user-menu-item" href="profile.php">
            <span>👤</span><span>Profil saya</span>
          </a>

          <a class="user-menu-item" href="subscription.php">
            <span>💾</span><span>Paket Penyimpanan</span>
          </a>

          <a class="user-menu-item" href="logout.php?to=auth">
            <span>🔐</span><span>Kelola akun / login lain</span>
          </a>

          <a class="user-menu-item danger" href="logout.php">
            <span>🚪</span><span>Logout</span>
          </a>
        </div>

      </div>
    </div>
  </div>

  <div class="toolbar">
    <button class="btn-primary" id="btnUpload">
      ⬆ Upload File
    </button>
    <input type="file" id="fileInput" multiple style="display:none">

    <button class="btn-soft" id="btnRefresh">🔄 Refresh</button>
    <button class="btn-soft" id="btnUpFolder">⬆ Up</button>

    <button class="btn-soft" id="btnSortName">🔠 Sort Nama</button>
    <button class="btn-soft" id="btnSortSize">📦 Sort Ukuran</button>
    <button class="btn-soft" id="btnSortDate">🕒 Sort Tanggal</button>

    <button class="btn-soft active-tab" id="btnTabFiles">File</button>
    <button class="btn-soft" id="btnTabTrash">Trash</button>

    <div class="input-search">
      <span>🔍</span>
      <input type="text" id="searchInput" placeholder="Cari nama file...">
    </div>
  </div>

 

    <div class="table-wrapper">
      <div class="table-header">
        <div class="table-header-left">
          <button id="btnBackTop" class="back-button" style="display:none;">⬅ Kembali</button>
          <span id="tableTitle">Daftar File</span>
          <div id="breadcrumb" class="breadcrumb"></div>
        </div>

        <div class="table-header-right" id="legendMain">
          <span>⬇ Download</span>
          <span>🔗 Share</span>
          <span>✏ Rename</span>
          <span>🗑 Trash</span>
        </div>
        <div class="table-header-right" id="legendTrash" style="display:none;">
          <span>↩ Restore</span>
          <span>❌ Hapus Permanen</span>
        </div>
      </div>
      <div class="table-scroll" id="fileTableContainer">
        <!-- filled by JS -->
      </div>
      <div class="table-scroll" id="trashTableContainer" style="display:none;">
        <!-- filled by JS -->
      </div>
    </div>
  </div>
</div>

<!-- MODAL UPLOAD -->
<div id="uploadModalOverlay" class="upload-modal-overlay">
  <div class="upload-modal">
    <div class="upload-modal-header">
      <div>
        <div class="upload-modal-title">Upload File</div>
        <div class="upload-modal-sub">
          File akan disimpan di folder
          <b>data/user_<?php echo $userId; ?>/</b>
          <span id="uploadModalPathLabel"></span>
        </div>
      </div>
      <button type="button" id="uploadModalCloseTop" class="upload-modal-close">✕</button>
    </div>

    <div id="uploadModalDropzone" class="upload-modal-dropzone">
      <div class="upload-modal-drop-icon">☁</div>
      <div>
        <div class="upload-modal-drop-title">Drag & drop file ke sini</div>
        <div class="upload-modal-drop-sub">
          Atau klik area ini / tombol di bawah untuk memilih file secara manual.
        </div>
      </div>
    </div>

    <div id="uploadProgressArea" class="upload-progress-wrapper" style="display:none;">
      <div id="uploadProgressText" class="upload-progress-text">
        Mengupload…
      </div>
      <div class="upload-progress-bar">
        <div id="uploadProgressBar" class="upload-progress-bar-inner"></div>
      </div>
    </div>

    <div class="upload-modal-footer">
      <button type="button" id="uploadModalSelectBtn" class="btn-primary">
        Pilih File
      </button>
      <button type="button" id="uploadModalCloseBottom" class="btn-soft">
        Tutup
      </button>
    </div>
  </div>
</div>

<script>
let fileData   = [];
let trashData  = [];
let sortBy     = "name"; // name|size|date
let sortDir    = "asc";  // asc|desc
let searchQuery = "";
let activeTab  = "files";
let currentPath = "";    // "" = root (data/user_X), "Folder1", "Folder1/Sub"

function humanSize(bytes) {
  if (bytes === 0) return "0 B";
  const units = ["B","KB","MB","GB","TB"];
  const i = Math.floor(Math.log(bytes)/Math.log(1024));
  const val = bytes / Math.pow(1024,i);
  return val.toFixed(val<10&&i>0?1:0)+" "+units[i];
}
function formatDate(ts) {
  const d = new Date(ts*1000);
  return d.toLocaleString("id-ID",{day:"2-digit",month:"short",hour:"2-digit",minute:"2-digit"});
}
function getPathDisplay() {
  return currentPath ? ("/" + currentPath) : "/";
}

// ===== MODAL UPLOAD =====
function openUploadModal() {
  const overlay = document.getElementById("uploadModalOverlay");
  const pathLbl = document.getElementById("uploadModalPathLabel");
  if (pathLbl) {
    pathLbl.textContent = currentPath ? (" /" + currentPath) : " /";
  }
  if (overlay) overlay.style.display = "flex";
}

function closeUploadModal() {
  const overlay = document.getElementById("uploadModalOverlay");
  if (overlay) overlay.style.display = "none";
}

// ===== BREADCRUMB =====
function renderBreadcrumb() {
  const bc = document.getElementById("breadcrumb");
  if (!bc) return;

  if (!currentPath) {
    // root
    bc.innerHTML = '<span data-path="">/</span>';
  } else {
    const parts = currentPath.split("/");
    let html = '<span data-path="">Root</span>';
    let build = "";
    parts.forEach((p, idx) => {
      if (!p) return;
      build = idx === 0 ? p : (build + "/" + p);
      html += ' / <span data-path="' + build.replace(/"/g,'&quot;') + '">' + p + '</span>';
    });
    bc.innerHTML = html;
  }

  bc.querySelectorAll("span").forEach(sp => {
    sp.addEventListener("click", () => {
      const p = sp.getAttribute("data-path") || "";
      currentPath = p;
      loadFiles();
    });
  });
}

// ===== LOAD FILES =====
async function loadFiles() {
  try {
    const res = await fetch("api.php?action=list&path=" + encodeURIComponent(currentPath));
    const json = await res.json();
    if (json.success) {
      fileData = json.files || [];
      renderFileTable();
    } else {
      alert("Gagal load: " + (json.error || "unknown"));
    }
  } catch(e) {
    console.error(e);
    alert("Error koneksi ke api.php");
  }
}

// ===== LOAD TRASH =====
async function loadTrash() {
  try {
    const res = await fetch("api.php?action=list_trash");
    const json = await res.json();
    if (json.success) {
      trashData = json.files || [];
      renderTrashTable();
    } else {
      alert("Gagal load trash: " + (json.error || "unknown"));
    }
  } catch(e) {
    console.error(e);
    alert("Error koneksi ke api.php");
  }
}

function getFilteredSorted() {
  let arr = fileData.slice();

  if (searchQuery.trim() !== "") {
    const q = searchQuery.trim().toLowerCase();
    arr = arr.filter(f => f.name.toLowerCase().includes(q));
  }

  arr.sort((a,b)=>{
    let v = 0;
    if (sortBy === "name") {
      v = a.name.localeCompare(b.name);
    } else if (sortBy === "size") {
      v = (a.size || 0) - (b.size || 0);
    } else if (sortBy === "date") {
      v = a.mtime - b.mtime;
    }
    return sortDir === "asc" ? v : -v;
  });

  return arr;
}

// ===== RENDER FILE TABLE =====
function renderFileTable() {
  const container = document.getElementById("fileTableContainer");
  const badge     = document.getElementById("badgeInfo");
  const data      = getFilteredSorted();

  badge.textContent = data.length + " item";

  // atur tombol kembali atas
  const backTopBtn = document.getElementById("btnBackTop");
  if (backTopBtn) {
    backTopBtn.style.display = currentPath ? "inline-flex" : "none";
  }

  if (data.length === 0) {
    container.innerHTML = '<div class="empty">Belum ada file. Upload beberapa file untuk memulai.</div>';
    // walau kosong, breadcrumb tetap ditampilkan
    renderBreadcrumb();
    return;
  }

  const rows = data.map(f => {
    const isDir     = f.type === "dir";
    const extUpper  = (f.ext || "").toUpperCase();
    const iconLabel = isDir ? "DIR" : (extUpper || "?").substring(0,3);
    const metaText  = isDir ? "Folder" : `${extUpper} · ${humanSize(f.size)}`;
    const sizeText  = isDir ? "-" : humanSize(f.size);
    const dateText  = formatDate(f.mtime);

    let actionsHtml = "";
    if (isDir) {
      actionsHtml = `
        <div class="actions">
          <button type="button" data-name="${f.name}" class="btn-open-folder">📁</button>
        </div>
      `;
    } else {
      const extLower = (f.ext || "").toLowerCase();
      const extractBtn = extLower === "zip"
        ? `<button type="button" data-name="${f.name}" class="btn-extract">📦</button>`
        : "";
      actionsHtml = `
        <div class="actions">
          <button type="button" data-name="${f.name}" class="btn-download">⬇</button>
          <button type="button" data-name="${f.name}" class="btn-preview">👁</button>
          <button type="button" data-name="${f.name}" class="btn-share">🔗</button>
          <button type="button" data-name="${f.name}" class="btn-rename">✏</button>
          ${extractBtn}
          <button type="button" data-name="${f.name}" class="btn-delete danger">🗑</button>
        </div>
      `;
    }

    return `
      <tr>
        <td>
          <div class="file-name-cell">
            <div class="file-icon">${iconLabel}</div>
            <div>
              <button
                type="button"
                class="file-name-link"
                data-name="${f.name}"
                data-type="${f.type}"
              >
                ${f.name}
              </button>
              <div class="file-meta">${metaText}</div>
            </div>
          </div>
        </td>
        <td>${sizeText}</td>
        <td>${dateText}</td>
        <td>${actionsHtml}</td>
      </tr>
    `;
  }).join("");

  container.innerHTML = `
    <table>
      <thead>
        <tr>
          <th>Nama</th>
          <th>Ukuran</th>
          <th>Update</th>
          <th style="text-align:right;">Aksi</th>
        </tr>
      </thead>
      <tbody>
        ${rows}
      </tbody>
    </table>
  `;

  // event untuk FILE
  container.querySelectorAll(".btn-delete").forEach(btn=>{
    btn.addEventListener("click", ()=> handleDelete(btn.getAttribute("data-name")));
  });
  container.querySelectorAll(".btn-rename").forEach(btn=>{
    btn.addEventListener("click", ()=> handleRename(btn.getAttribute("data-name")));
  });
  container.querySelectorAll(".btn-download").forEach(btn=>{
    btn.addEventListener("click", ()=> downloadFile(btn.getAttribute("data-name")));
  });
  container.querySelectorAll(".btn-share").forEach(btn=>{
    btn.addEventListener("click", ()=> shareFile(btn.getAttribute("data-name")));
  });
  container.querySelectorAll(".btn-preview").forEach(btn=>{
    btn.addEventListener("click", ()=> previewFile(btn.getAttribute("data-name")));
  });
  container.querySelectorAll(".btn-extract").forEach(btn=>{
    btn.addEventListener("click", ()=> extractZip(btn.getAttribute("data-name")));
  });

  // event untuk FOLDER (ikon 📁)
  container.querySelectorAll(".btn-open-folder").forEach(btn=>{
    btn.addEventListener("click", () => {
      const name = btn.getAttribute("data-name");
      if (!name) return;
      currentPath = currentPath ? (currentPath + "/" + name) : name;
      loadFiles();
    });
  });

  // event klik pada NAMA (file & folder)
  container.querySelectorAll(".file-name-link").forEach(btn => {
    btn.addEventListener("click", () => {
      const name = btn.getAttribute("data-name");
      const type = btn.getAttribute("data-type");
      if (!name) return;

      if (type === "dir") {
        currentPath = currentPath ? (currentPath + "/" + name) : name;
        loadFiles();
      } else {
        previewFile(name);
      }
    });
  });

  // render breadcrumb
  renderBreadcrumb();
}

// fungsi naik 1 level folder
function goUpFolder() {
  if (!currentPath) return; // sudah di root
  const parts = currentPath.split("/");
  parts.pop();
  currentPath = parts.join("/");
  loadFiles();
}

// tombol Up Folder toolbar
document.getElementById("btnUpFolder").onclick = goUpFolder;

// tombol Kembali di atas list
const btnBackTopEl = document.getElementById("btnBackTop");
if (btnBackTopEl) {
  btnBackTopEl.onclick = goUpFolder;
}

// ===== RENDER TRASH TABLE =====
function renderTrashTable() {
  const container = document.getElementById("trashTableContainer");

  if (trashData.length === 0) {
    container.innerHTML = '<div class="empty">Trash kosong.</div>';
    return;
  }

  const rows = trashData.map(f => `
    <tr>
      <td>
        <div class="file-name-cell">
          <div class="file-icon">DEL</div>
          <div>
            <div>${f.name}</div>
            <div class="file-meta">${humanSize(f.size)}</div>
          </div>
        </div>
      </td>
      <td>${humanSize(f.size)}</td>
      <td>${formatDate(f.mtime)}</td>
      <td>
        <div class="actions">
          <button type="button" data-name="${f.name}" class="btn-restore">↩</button>
          <button type="button" data-name="${f.name}" class="btn-delete-perm danger">❌</button>
        </div>
      </td>
    </tr>
  `).join("");

  container.innerHTML = `
    <table>
      <thead>
        <tr>
          <th>Nama (Trash)</th>
          <th>Ukuran</th>
          <th>Dihapus</th>
          <th style="text-align:right;">Aksi</th>
        </tr>
      </thead>
      <tbody>
        ${rows}
      </tbody>
    </table>
  `;

  container.querySelectorAll(".btn-restore").forEach(btn=>{
    btn.addEventListener("click", ()=> handleRestore(btn.getAttribute("data-name")));
  });
  container.querySelectorAll(".btn-delete-perm").forEach(btn=>{
    btn.addEventListener("click", ()=> handleDeletePerm(btn.getAttribute("data-name")));
  });
}

// ===== ACTIONS =====
async function handleDelete(name) {
  if (!confirm('Pindahkan file "'+name+'" ke Trash?')) return;
  const form = new FormData();
  form.append("action","delete");
  form.append("name",name);
  form.append("path", currentPath);

  const res = await fetch("api.php",{ method:"POST", body:form });
  const json = await res.json();
  if (json.success) {
    await loadFiles();
    await loadTrash();
  } else {
    alert("Gagal hapus: " + (json.error || "unknown"));
  }
}

async function handleRename(oldName) {
  const newName = prompt("Nama baru untuk:", oldName);
  if (!newName || newName === oldName) return;

  const form = new FormData();
  form.append("action","rename");
  form.append("oldName",oldName);
  form.append("newName",newName);
  form.append("path", currentPath);

  const res = await fetch("api.php",{ method:"POST", body:form });
  const json = await res.json();
  if (json.success) {
    await loadFiles();
  } else {
    alert("Gagal rename: " + (json.error || "unknown"));
  }
}

function downloadFile(name) {
  window.location =
    "api.php?action=download&file=" + encodeURIComponent(name) +
    "&path=" + encodeURIComponent(currentPath);
}

function previewFile(name) {
  // NOTE: preview.php perlu kamu update sendiri supaya paham 'path'
  window.open("preview.php?file=" + encodeURIComponent(name), "_blank");
}

async function extractZip(name) {
  if (!confirm('Extract file ZIP "'+name+'"?')) return;

  const fd = new FormData();
  fd.append("action", "extract_zip");
  fd.append("name", name);
  fd.append("path", currentPath);

  const res  = await fetch("api.php", { method:"POST", body:fd });
  const json = await res.json();
  if (!json.success) {
    alert("Gagal extract ZIP: " + (json.error || "unknown"));
    return;
  }
  alert("ZIP berhasil di-extract.");
  await loadFiles();
}

async function shareFile(name) {
  // backend kita batasi share hanya di root (relPath == "")
  if (currentPath) {
    alert("Share link saat ini hanya bisa dari folder utama.");
    return;
  }

  const modeInput = (prompt('Mode share? (download/view)','download') || 'download').toLowerCase();
  const daysStr   = prompt('Expired berapa hari? (0 = tidak kadaluarsa)','0') || '0';
  const days      = parseInt(daysStr,10) || 0;

  const fd = new FormData();
  fd.append("action","create_share");
  fd.append("name",name);
  fd.append("mode", modeInput === 'view' ? 'view' : 'download');
  fd.append("expires_days", days);

  const res  = await fetch("api.php",{ method:"POST", body:fd });
  const json = await res.json();
  if (!json.success) {
    alert("Gagal membuat share: " + (json.error || "unknown"));
    return;
  }
  alert(
    "Link share:\n" + json.url +
    "\nMode: " + json.mode +
    (json.expires ? ("\nExpired: " + json.expires) : "")
  );
}

async function handleRestore(name) {
  const fd = new FormData();
  fd.append("action","restore");
  fd.append("name",name);
  const res  = await fetch("api.php",{ method:"POST", body:fd });
  const json = await res.json();
  if (!json.success) {
    alert("Gagal restore: " + (json.error || "unknown"));
    return;
  }
  await loadTrash();
  await loadFiles();
}

async function handleDeletePerm(name) {
  if (!confirm('Hapus permanen file "'+name+'"?')) return;
  const fd = new FormData();
  fd.append("action","delete_trash");
  fd.append("name",name);
  const res  = await fetch("api.php",{ method:"POST", body:fd });
  const json = await res.json();
  if (!json.success) {
    alert("Gagal hapus permanen: " + (json.error || "unknown"));
    return;
  }
  await loadTrash();
}

// ===== UPLOAD DENGAN PROGRESS (pakai MODAL) =====
function uploadFiles(list) {
  if (!list || list.length === 0) return;

  // pastikan modal tampil
  openUploadModal();

  const progressArea = document.getElementById("uploadProgressArea");
  const progressText = document.getElementById("uploadProgressText");
  const progressBar  = document.getElementById("uploadProgressBar");

  if (progressArea) progressArea.style.display = "block";
  if (progressBar)  progressBar.style.width = "0%";
  if (progressText) progressText.textContent = "Mengupload… 0%";

  const fd = new FormData();
  fd.append("action","upload");
  fd.append("path", currentPath);
  for (const f of list) {
    fd.append("files[]", f);
  }

  const xhr = new XMLHttpRequest();
  xhr.open("POST", "api.php", true);

  xhr.upload.onprogress = function (e) {
    if (!e.lengthComputable) return;
    const percent = Math.round((e.loaded / e.total) * 100);

    if (progressBar)  progressBar.style.width = percent + "%";
    if (progressText) progressText.textContent = "Mengupload… " + percent + "%";
  };

  xhr.onload = function () {
    if (progressText) progressText.textContent = "Memproses respon server…";

    let json = null;
    try {
      json = JSON.parse(xhr.responseText);
    } catch (err) {
      console.error("Response bukan JSON:", xhr.responseText);
      alert("Gagal parsing respon server saat upload.");
      if (progressArea) progressArea.style.display = "none";
      return;
    }

    if (xhr.status === 200 && json && json.success) {
      if (progressText) progressText.textContent = "Upload selesai ✅";
      loadFiles();
    } else {
      const msg = (json && json.error) ? json.error : ("HTTP " + xhr.status);
      alert("Gagal upload: " + msg);
    }

    setTimeout(() => {
      if (progressArea) progressArea.style.display = "none";
      // kalau mau modal menutup otomatis:
      // closeUploadModal();
    }, 900);
  };

  xhr.onerror = function () {
    alert("Terjadi error jaringan saat upload.");
    if (progressArea) progressArea.style.display = "none";
  };

  xhr.send(fd);
}

// Upload & toolbar events
document.getElementById("btnUpload").onclick = () => {
  openUploadModal();
};

document.getElementById("fileInput").onchange = e => {
  if (e.target.files.length > 0) uploadFiles(e.target.files);
};

document.getElementById("btnRefresh").onclick = () => {
  if (activeTab === "files") loadFiles(); else loadTrash();
};

document.getElementById("btnSortName").onclick = () => {
  sortBy = "name";
  sortDir = sortDir === "asc" ? "desc" : "asc";
  renderFileTable();
};
document.getElementById("btnSortSize").onclick = () => {
  sortBy = "size";
  sortDir = sortDir === "asc" ? "desc" : "asc";
  renderFileTable();
};
document.getElementById("btnSortDate").onclick = () => {
  sortBy = "date";
  sortDir = sortDir === "asc" ? "desc" : "asc";
  renderFileTable();
};

document.getElementById("searchInput").addEventListener("input", e=>{
  searchQuery = e.target.value;
  if (activeTab === "files") renderFileTable();
});

// dropzone utama
const dropzone = document.getElementById("dropzone");
if (dropzone) {
  dropzone.addEventListener("click", () => {
    openUploadModal();
  });
  ["dragenter","dragover"].forEach(evt=>{
    dropzone.addEventListener(evt, e=>{
      e.preventDefault(); e.stopPropagation();
      dropzone.classList.add("dragover");
    });
  });
  ["dragleave","drop"].forEach(evt=>{
    dropzone.addEventListener(evt, e=>{
      e.preventDefault(); e.stopPropagation();
      dropzone.classList.remove("dragover");
    });
  });
  dropzone.addEventListener("drop", e=>{
    const files = e.dataTransfer.files;
    if (files && files.length>0) uploadFiles(files);
  });
}

// MODAL upload: tombol & dropzone
const uploadModalDropzone = document.getElementById("uploadModalDropzone");
const uploadModalSelectBtn = document.getElementById("uploadModalSelectBtn");
const uploadModalCloseTop  = document.getElementById("uploadModalCloseTop");
const uploadModalCloseBottom = document.getElementById("uploadModalCloseBottom");

if (uploadModalSelectBtn) {
  uploadModalSelectBtn.onclick = () => {
    document.getElementById("fileInput").click();
  };
}

if (uploadModalDropzone) {
  uploadModalDropzone.addEventListener("click", () => {
    document.getElementById("fileInput").click();
  });

  ["dragenter","dragover"].forEach(evt => {
    uploadModalDropzone.addEventListener(evt, e => {
      e.preventDefault();
      e.stopPropagation();
      uploadModalDropzone.classList.add("dragover");
    });
  });
  ["dragleave","drop"].forEach(evt => {
    uploadModalDropzone.addEventListener(evt, e => {
      e.preventDefault();
      e.stopPropagation();
      uploadModalDropzone.classList.remove("dragover");
    });
  });
  uploadModalDropzone.addEventListener("drop", e => {
    const files = e.dataTransfer.files;
    if (files && files.length > 0) {
      uploadFiles(files);
    }
  });
}

if (uploadModalCloseTop) uploadModalCloseTop.onclick = closeUploadModal;
if (uploadModalCloseBottom) uploadModalCloseBottom.onclick = closeUploadModal;

// USER MENU JS
const avatarBtn = document.getElementById("userAvatarBtn");
const dropdown  = document.getElementById("userDropdown");

avatarBtn.addEventListener("click", (e)=>{
  e.stopPropagation();
  dropdown.classList.toggle("open");
});

document.addEventListener("click", ()=>{
  dropdown.classList.remove("open");
});

// TAB FILE / TRASH
const btnTabFiles  = document.getElementById("btnTabFiles");
const btnTabTrash  = document.getElementById("btnTabTrash");
const fileTableBox = document.getElementById("fileTableContainer");
const trashTableBox= document.getElementById("trashTableContainer");
const legendMain   = document.getElementById("legendMain");
const legendTrash  = document.getElementById("legendTrash");
const tableTitle   = document.getElementById("tableTitle");

btnTabFiles.onclick = () => {
  activeTab = "files";
  btnTabFiles.classList.add("active-tab");
  btnTabTrash.classList.remove("active-tab");
  fileTableBox.style.display  = "";
  trashTableBox.style.display = "none";
  legendMain.style.display    = "";
  legendTrash.style.display   = "none";
  tableTitle.textContent      = "Daftar File";
  renderFileTable();
};

btnTabTrash.onclick = () => {
  activeTab = "trash";
  btnTabTrash.classList.add("active-tab");
  btnTabFiles.classList.remove("active-tab");
  fileTableBox.style.display  = "none";
  trashTableBox.style.display = "";
  legendMain.style.display    = "none";
  legendTrash.style.display   = "";
  tableTitle.textContent      = "Trash";
  loadTrash();
};

// init
loadFiles();
</script>
</body>
</html>

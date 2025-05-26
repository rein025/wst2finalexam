<?php
session_start();
if (empty($_SESSION['username'])) {
    header('Location: login.php');
    exit;
}

$xmlFile = 'students.xml';
$students = file_exists($xmlFile)
    ? simplexml_load_file($xmlFile)
    : new SimpleXMLElement('<students/>');

function saveXml($xml, $file) {
    $xml->asXML($file);
}

$action = $_POST['action'] ?? '';
$query = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($action) {
        case 'add':
            $photoFilename = '';
            if (!empty($_FILES['photoFile']['name'])) {
                $uploadDir = 'profiles/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                $ext = pathinfo($_FILES['photoFile']['name'], PATHINFO_EXTENSION);
                $photoFilename = trim($_POST['id']) . '.' . $ext;
                move_uploaded_file($_FILES['photoFile']['tmp_name'], $uploadDir . $photoFilename);
            }
            $new = $students->addChild('student');
            $new->addChild('id', trim($_POST['id']));
            $new->addChild('name', htmlspecialchars(trim($_POST['name'])));
            $new->addChild('course', htmlspecialchars(trim($_POST['course'])));
            $new->addChild('photo', $photoFilename);
            saveXml($students, $xmlFile);
            break;
        case 'delete':
            $delId = trim($_POST['student_id']);
            for ($i = 0; $i < count($students->student); $i++) {
                if ((string)$students->student[$i]->id === $delId) {
                    $photo = (string)$students->student[$i]->photo;
                    if ($photo && file_exists("profiles/$photo")) unlink("profiles/$photo");
                    unset($students->student[$i]);
                    saveXml($students, $xmlFile);
                    break;
                }
            }
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) { echo 'OK'; exit; }
            break;
        case 'edit':
            $origId = trim($_POST['orig_id']);
            foreach ($students->student as $st) {
                if ((string)$st->id === $origId) {
                    if (!empty($_FILES['photoFile']['name'])) {
                        $oldPhoto = (string)$st->photo;
                        if ($oldPhoto && file_exists("profiles/$oldPhoto")) unlink("profiles/$oldPhoto");
                        $uploadDir = 'profiles/';
                        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                        $ext = pathinfo($_FILES['photoFile']['name'], PATHINFO_EXTENSION);
                        $newPhoto = trim($_POST['id']) . '.' . $ext;
                        move_uploaded_file($_FILES['photoFile']['tmp_name'], $uploadDir . $newPhoto);
                        $st->photo = $newPhoto;
                    }
                    $st->id = trim($_POST['id']);
                    $st->name = htmlspecialchars(trim($_POST['name']));
                    $st->course = htmlspecialchars(trim($_POST['course']));
                    saveXml($students, $xmlFile);
                    break;
                }
            }
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) { echo 'OK'; exit; }
            break;
        case 'search':
            $query = trim($_POST['query']);
            break;
    }
    if ($action !== 'search' && empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
        header('Location: ' . $_SERVER['PHP_SELF']); exit;
    }
}

$display = [];
foreach ($students->student as $st) {
    if ($query === ''
        || stripos($st->id, $query) !== false
        || stripos($st->name, $query) !== false
        || stripos($st->course, $query) !== false
    ) {
        $photoFile = trim((string)$st->photo);
        if ($photoFile === '' || !file_exists("profiles/$photoFile")) {
            $st->photoUrl = 'profiles/placeholder.jpg';
        } else {
            $st->photoUrl = "profiles/$photoFile";
        }
        $display[] = $st;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Firefox League | BSU Malolos Campus - Member List</title>
<style>
    *, *::before, *::after { box-sizing:border-box; margin:0; padding:0; }
    body { font-family:'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background:#eef2f7; color:#333; }
    .container { padding:80px 20px 20px; max-width:900px; margin:0 auto; }
    .menu-bar { position:fixed; top:0; left:0; right:0; height:60px; background:linear-gradient(90deg,#fd7251,#fdb65a); display:flex; align-items:center; justify-content:space-between; padding:0 20px; box-shadow:0 4px 12px rgba(0,0,0,0.15); z-index:1000; }
    .menu-bar .logo { color:#fff; font-size:1.6em; font-weight:bold; letter-spacing:1px; transition:opacity .5s; }
    .menu-bar nav a { color:rgba(255,255,255,0.9); text-decoration:none; margin-left:25px; position:relative; transition:transform .3s, color .3s; }
    .menu-bar nav a::after { content:''; position:absolute; bottom:-4px; left:0; right:0; height:2px; background:#fff; transform:scaleX(0); transform-origin:left; transition:transform .3s; }
    .menu-bar nav a:hover { color:#fff; transform:translateY(-2px); }
    .menu-bar nav a:hover::after { transform:scaleX(1); }
    h1 { text-align:center; margin-bottom:20px; }
    .controls { display:flex; justify-content:space-between; align-items:center; margin-bottom:15px; }
    .controls button, #searchForm button { background:#c348df; color:#fff; border:none; padding:10px 16px; border-radius:6px; cursor:pointer; transition:transform .2s; }
    .controls button:hover, #searchForm button:hover { transform:translateY(-2px); }
    #searchForm input { padding:9.4px; border:1px solid #ccc; border-radius:6px 0 0 6px; }
    #searchForm button { border-radius:0 6px 6px 0; }
    table { width:100%; border-collapse:collapse; background:#fff; border-radius:8px; overflow:hidden; box-shadow:0 4px 12px rgba(0,0,0,0.05); }
    th, td { padding:14px; border-bottom:1px solid #f1f3f5; text-align:center; }
    th { background:#f8f9fa; }
    tr { transition: background-color 0.3s ease; }
    tr:hover { background:#f8f9fa; cursor:pointer; }
    img.photo {
        width:50px;
        height:50px;
        object-fit:cover;
        border-radius:8px;
        transition: transform 0.3s ease-in-out;
    }
    img.photo:hover {
        transform: scale(1.4);
        position: relative;
    }
    .modal { display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.4); align-items:center; justify-content:center; animation:fadeInOverlay .3s ease; }
    .modal.show { display:flex; }
    .modal-content { background:#fff; padding:24px; border-radius:12px; width:90%; max-width:360px; position:relative; animation:zoomIn .3s ease; }
    .close { position:absolute; top:12px; right:16px; font-size:1.4em; cursor:pointer; }
    @keyframes zoomIn { from { transform:scale(.8); opacity:0; } to { transform:scale(1); opacity:1; }}
    @keyframes fadeInOverlay { from { background:rgba(0,0,0,0);} to { background:rgba(0,0,0,0.4);} }
    .modal-content label { display:block; margin:12px 0 6px; }
    .modal-content input, .modal-content select { width:100%; padding:10px; border:1px solid #ddd; border-radius:6px; }
    .modal-content button { width:100%; padding:10px; margin-top:12px; }
</style>
</head>
<body>
    <div class="menu-bar">
        <div class="logo" id="logoText">Web Systems and Technologies II</div>
        <nav>
            <a href="#">Home</a>
            <a href="about.html">About</a>
            <a href="login.php">Logout</a>
        </nav>
    </div>
    <div class="container">
        <h1>Firefox Club | BSU Malolos Campus - Member List</h1>
        <div class="controls">
            <div class="left-controls">
                <button onclick="openModal('addModal')">Add</button>
                <button onclick="openModal('deleteModal')">Delete</button>
            </div>
            <form id="searchForm" method="post">
                <input type="hidden" name="action" value="search">
                <input type="text" name="query" placeholder="Search Student" value="<?=htmlspecialchars($query)?>">
                <button type="submit">Search</button>
            </form>
        </div>
        <table>
            <thead><tr><th>ID</th><th>Photo</th><th>Name</th><th>Course</th></tr></thead>
            <tbody id="studentBody">
                <?php foreach($display as $st): ?><tr data-id="<?=htmlspecialchars($st->id)?>">
                    <td><?=htmlspecialchars($st->id)?></td>
                    <td><img class="photo" src="<?=htmlspecialchars($st->photoUrl)?>" alt="Photo"></td>
                    <td><?=htmlspecialchars($st->name)?></td>
                    <td><?=htmlspecialchars($st->course)?></td>
                </tr><?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div id="addModal" class="modal"><div class="modal-content">
        <span class="close" onclick="closeModal('addModal')">&times;</span>
        <h2>Add Student</h2>
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="action" value="add">
            <label>ID:</label><input type="text" name="id" required>
            <label>Name:</label><input type="text" name="name" required>
            <label>Course:</label><input type="text" name="course" required>
            <label>Photo Upload:</label><input type="file" name="photoFile" accept="image/*">
            <button type="submit">Add Student</button>
        </form>
    </div></div>
    <div id="deleteModal" class="modal"><div class="modal-content">
        <span class="close" onclick="closeModal('deleteModal')">&times;</span>
        <h2>Delete Student</h2>
        <form id="deleteForm">
            <label>Select Student:</label>
            <select id="deleteSelect" name="student_id" required>
    <option value="">Choose Here</option>
    <?php foreach($students->student as $st): ?>
        <option value="<?=htmlspecialchars($st->id)?>"><?=htmlspecialchars($st->name)?></option>
    <?php endforeach; ?>
</select>
            <button type="submit">Delete Student</button>
        </form>
    </div></div>
    <div id="editModal" class="modal"><div class="modal-content">
        <span class="close" onclick="closeModal('editModal')">&times;</span>
        <h2>Edit Student</h2>
        <form id="editForm" method="post" enctype="multipart/form-data">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="orig_id" id="origId">
            <label>ID:</label><input type="text" name="id" id="editId" required>
            <label>Name:</label><input type="text" name="name" id="editName" required>
            <label>Course:</label><input type="text" name="course" id="editCourse" required>
            <label>Upload New Photo:</label><input type="file" name="photoFile" accept="image/*">
            <button type="submit">Update Changes</button>
        </form>
    </div></div>
    <script>
        document.getElementById('deleteForm').addEventListener('submit', function(e) {
            e.preventDefault(); const id=document.getElementById('deleteSelect').value; if(!id)return;
            fetch('index.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded','X-Requested-With':'XMLHttpRequest'},body:`action=delete&student_id=${encodeURIComponent(id)}`})
            .then(()=>{document.querySelector(`tr[data-id='${id}']`)?.remove();document.querySelector(`#deleteSelect option[value='${id}']`)?.remove();closeModal('deleteModal');});
        });
        document.getElementById('studentBody').addEventListener('click',function(e){const tr=e.target.closest('tr[data-id]');if(!tr)return;
            document.getElementById('origId').value=tr.dataset.id;
            document.getElementById('editId').value=tr.cells[0].textContent;
            document.getElementById('editName').value=tr.cells[2].textContent;
            document.getElementById('editCourse').value=tr.cells[3].textContent;
            openModal('editModal');
        });
        document.getElementById('editForm').addEventListener('submit',function(e){e.preventDefault();const orig=document.getElementById('origId').value;
            const id=document.getElementById('editId').value,name=document.getElementById('editName').value,course=document.getElementById('editCourse').value;
            const formData=new FormData(this);formData.append('action','edit');formData.append('orig_id',orig);
            fetch('index.php',{method:'POST',body:formData,headers:{'X-Requested-With':'XMLHttpRequest'}})
            .then(()=>{const row=document.querySelector(`tr[data-id='${orig}']`);
                row.cells[0].textContent=id;
                if(this.photoFile.files.length){row.cells[1].querySelector('img').src=row.cells[1].querySelector('img').src.split('/').slice(0,-1).join('/')+'/'+id+'.'+this.photoFile.files[0].name.split('.').pop();}
                row.cells[2].textContent=name;
                row.cells[3].textContent=course;
                row.dataset.id=id;
                closeModal('editModal');
            });
        });
        const logoEl=document.getElementById('logoText'),texts=['Web Systems and Technologies II','Eusebio, Reiner Kirsten C.','Medina, Fiona Gabrielle V.','BSIT 3B - G2'];let idx=0;
        setInterval(()=>{logoEl.style.opacity=0;setTimeout(()=>{idx=(idx+1)%texts.length;logoEl.textContent=texts[idx];logoEl.style.opacity=1;},500);},10000);
        function openModal(id){document.getElementById(id).classList.add('show');}
        function closeModal(id){document.getElementById(id).classList.remove('show');}
        window.onclick=e=>['addModal','deleteModal','editModal'].forEach(id=>{if(e.target.id===id)closeModal(id);});
    </script>
</body>
</html>

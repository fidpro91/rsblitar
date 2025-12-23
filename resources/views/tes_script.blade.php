<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
<script>
 $(document).ready(()=> {
    let visit_id = 1034176;
    let urlSign = "http://129.29.77.119:81/api/tte/sign_tte";
    let urlResume = "https://08d6aff5a984.ngrok-free.app/rsblitar/public/contoh";
    $.ajax({
        url: urlSign,
        type: "POST",
        data: {
            nik: "3504032811940001",
            passphrase: "Al!5028Bayu",
            jenis_berkas: "docx",
            berkas: "resume_medis",
            id_berkas: 1,
            visit_id: visit_id,
            url: urlResume
        },
        success: function(response) {
            console.log("Success:", response);
        },
        error: function(xhr) {
            console.log("Error:", xhr.responseText);
        }
    });
 })
</script>
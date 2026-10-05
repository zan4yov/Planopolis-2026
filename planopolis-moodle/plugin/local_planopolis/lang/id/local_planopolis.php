<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Indonesian strings for local_planopolis.
 *
 * @package    local_planopolis
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['action_create'] = 'Buat akun';
$string['action_enrolonly'] = 'Sudah ada – hanya didaftarkan ke kursus';
$string['action_update'] = 'Perbarui + reset password';
$string['addquiz'] = 'Tambah quiz / babak';
$string['admins_add'] = 'Berikan akses Admin Quiz';
$string['admins_added'] = '{$a} sekarang memiliki akses Admin Quiz.';
$string['admins_addhelp'] = 'Ketik username akun yang sudah ada, atau isi semua kolom untuk membuat akun admin baru.';
$string['admins_confirmremove'] = 'Cabut akses Admin Quiz dari pengguna ini?';
$string['admins_created'] = 'Akun {$a} dibuat.';
$string['admins_intro'] = 'Admin Quiz dapat mendaftarkan peserta, mengimpor soal, mengecek quiz, dan mengunduh nilai. Halaman ini hanya dapat dibuka Super Admin (administrator situs).';
$string['admins_neednew'] = 'Username tersebut belum ada. Untuk membuat akun admin baru, isi juga nama dan password.';
$string['admins_newname'] = 'Nama lengkap (khusus akun baru)';
$string['admins_newpassword'] = 'Password (khusus akun baru)';
$string['admins_none'] = 'Belum ada Admin Quiz.';
$string['admins_norole'] = 'Role Admin Quiz belum ada. Jalankan skrip setup: php public/local/planopolis/cli/setup.php';
$string['admins_remove'] = 'Cabut akses';
$string['admins_removed'] = 'Akses dicabut.';
$string['attemptsmade'] = 'Pengerjaan';
$string['audit'] = 'Cek Quiz';
$string['audit_attempts'] = 'Peserta boleh mengerjakan lebih dari satu kali. Lomba biasanya hanya 1 kali.';
$string['audit_draft'] = 'Soal berstatus Draft dan TIDAK akan muncul ke peserta. Ubah statusnya menjadi Ready.';
$string['audit_duplicatequestion'] = 'Teks soal sama dengan soal nomor {$a}.';
$string['audit_emptyoption'] = 'Opsi {$a} kosong.';
$string['audit_errors'] = '{$a} masalah';
$string['audit_fail'] = 'Ditemukan {$a->errors} masalah dan {$a->warnings} peringatan pada {$a->slots} soal. Perbaiki masalah sebelum ujian.';
$string['audit_fewoptions'] = 'Opsi kurang dari dua.';
$string['audit_general'] = 'Pengaturan quiz';
$string['audit_hidden'] = 'Soal disembunyikan di bank soal.';
$string['audit_intro'] = 'Pemeriksaan ini mengecek semua soal di quiz, termasuk soal yang diketik lewat web. Fokusnya pada masalah 2024: soal yang tidak muncul dan soal dengan opsi jawaban ganda.';
$string['audit_manycorrect'] = '{$a} opsi ditandai benar 100%. Biasanya ini kesalahan.';
$string['audit_missing'] = 'Soal pada nomor ini sudah tidak ada – peserta akan melihat error. Hapus nomor ini lalu tambahkan soalnya lagi.';
$string['audit_multipleanswers'] = 'Soal mengizinkan lebih dari satu jawaban; lomba memakai pilihan ganda satu jawaban.';
$string['audit_noclose'] = 'Quiz tidak punya waktu tutup. Atur "Tutup quiz" agar pengerjaan yang terlambat otomatis dikumpulkan.';
$string['audit_nocorrect'] = 'Tidak ada opsi yang ditandai benar.';
$string['audit_noquestions'] = 'Quiz belum memiliki soal.';
$string['audit_nosumgrades'] = 'Total nilai quiz adalah 0.';
$string['audit_notmc'] = 'Jenis soal "{$a}", bukan pilihan ganda.';
$string['audit_ok'] = 'Siap';
$string['audit_pass'] = 'Tidak ada masalah pada {$a->slots} soal ({$a->warnings} peringatan).';
$string['audit_problem'] = 'Masalah';
$string['audit_randommixed'] = 'Soal acak dicampur dengan soal tetap; soal acak bisa mengambil soal yang sudah ada di nomor lain.';
$string['audit_randomshort'] = 'Soal acak ini butuh {$a->need} soal tetapi hanya tersedia {$a->have} – sebagian peserta akan mendapat soal yang hilang.';
$string['audit_reviewleak'] = 'Peserta dapat melihat kunci jawaban saat atau segera setelah mengerjakan. Jawaban bisa bocor ke peserta lain.';
$string['audit_severity'] = 'Tingkat';
$string['audit_slot'] = 'Soal {$a}';
$string['audit_slots'] = 'Soal acak {$a}';
$string['audit_where'] = 'Lokasi';
$string['audit_zeromark'] = 'Soal bernilai 0 poin.';
$string['card_addquiz'] = 'Buat quiz lain, misalnya untuk babak atau sesi berbeda.';
$string['card_admins'] = 'Berikan atau cabut akses Admin Quiz (khusus Super Admin).';
$string['card_audit'] = 'Jalankan sebelum setiap ujian: mendeteksi soal hilang, opsi ganda, dan kunci jawaban salah.';
$string['card_participantlist'] = 'Lihat, ubah, nonaktifkan, atau reset password peserta.';
$string['card_participants'] = 'Buat akun peserta dari Excel. Peserta tidak bisa mendaftar sendiri.';
$string['card_questions'] = 'Unggah soal dari template Excel, dengan gambar dan audio dalam ZIP.';
$string['card_results'] = 'Peringkat nilai beserta jumlah benar / salah / kosong, bisa diunduh sebagai Excel.';
$string['checkfile'] = 'Periksa file';
$string['checklist'] = 'Sebelum hari ujian';
$string['checklist_items'] = '<li>Daftarkan peserta dan bagikan akun yang diunduh.</li><li>Impor soal (atau ketik lewat editor web).</li><li>Atur waktu buka, waktu tutup, dan batas waktu di pengaturan quiz.</li><li>Jalankan Cek Quiz sampai tidak ada masalah.</li><li>Coba kerjakan quiz dengan akun peserta uji.</li><li>Setelah ujian, unduh nilai dari menu Rekap Nilai.</li>';
$string['closes'] = 'Tutup';
$string['col_audio'] = 'Audio Soal';
$string['col_category'] = 'Kategori';
$string['col_feedback'] = 'Pembahasan';
$string['col_image'] = 'Gambar Soal';
$string['col_key'] = 'Kunci';
$string['col_mark'] = 'Nilai Benar';
$string['col_no'] = 'No';
$string['col_optA'] = 'Opsi A';
$string['col_optB'] = 'Opsi B';
$string['col_penalty'] = 'Nilai Salah';
$string['col_question'] = 'Soal';
$string['confirmimport'] = 'Impor {$a} soal';
$string['confirmparticipants'] = 'Daftarkan {$a} peserta';
$string['credentialsnote'] = 'Unduh daftar akun sekarang. Demi keamanan, password tidak disimpan dalam bentuk yang bisa dibaca dan tidak bisa ditampilkan lagi setelah sesi ini berakhir.';
$string['downloadcredentials'] = 'Unduh daftar akun (Excel)';
$string['downloadexcel'] = 'Unduh Excel';
$string['downloadlastcredentials'] = 'Unduh daftar akun pendaftaran terakhir';
$string['editquiz'] = 'Buka daftar soal quiz';
$string['err_badaudioext'] = 'Audio "{$a}" harus berformat mp3, wav, ogg, atau m4a.';
$string['err_bademail'] = 'Email "{$a}" tidak valid.';
$string['err_badimageext'] = 'Gambar "{$a}" harus berformat png, jpg, jpeg, gif, webp, atau svg.';
$string['err_badkey'] = 'Kunci jawaban "{$a}" tidak valid. Gunakan satu huruf A–E.';
$string['err_badmark'] = 'Nilai Benar "{$a}" harus angka lebih dari 0.';
$string['err_badpenalty'] = 'Nilai Salah "{$a}" harus 0 atau angka negatif (misalnya -1).';
$string['err_badusername'] = 'Username "{$a}" hanya boleh berisi huruf kecil, angka, dan . - _ @';
$string['err_cannotread'] = 'File tidak dapat dibaca: {$a}';
$string['err_duplicateoption'] = 'Opsi {$a->a} dan {$a->b} sama ("{$a->text}").';
$string['err_dupusername'] = 'Username sama dengan baris {$a}.';
$string['err_emptyquestion'] = 'Teks soal kosong.';
$string['err_fewoptions'] = 'Minimal opsi A dan B harus diisi.';
$string['err_fixfirst'] = 'Perbaiki dulu kesalahan pada file.';
$string['err_importfailed'] = 'Impor gagal: {$a}';
$string['err_isadmin'] = 'Username ini milik administrator situs.';
$string['err_keyempty'] = 'Kunci jawaban {$a} tetapi opsi {$a} kosong.';
$string['err_medianotfound'] = 'File "{$a}" tidak ada di ZIP. Unggah ZIP berisi file Excel dan semua gambar/audio.';
$string['err_missingcolumns'] = 'Kolom wajib tidak ditemukan: {$a}. Gunakan template.';
$string['err_nokey'] = 'Kunci jawaban kosong.';
$string['err_noname'] = 'Nama kosong.';
$string['err_noparticipants'] = 'Tidak ada peserta di file.';
$string['err_noquestions'] = 'Tidak ada soal di file.';
$string['err_nousername'] = 'Username kosong.';
$string['err_optiongap'] = 'Opsi {$a} diisi tetapi ada opsi sebelumnya yang kosong.';
$string['err_penaltyratio'] = 'Nilai Salah {$a->penalty} dengan Nilai Benar {$a->mark} tidak didukung Moodle. Rasio yang didukung misalnya -1/4, -1/3, -1/2, -1/5, -1/10, atau -1/1 dari Nilai Benar.';
$string['err_passwordpolicy'] = 'Password tidak memenuhi aturan password situs: {$a} Kosongkan sel Password agar dibuat otomatis.';
$string['err_quizhasattempts'] = 'Quiz ini sudah ada yang mengerjakan, sehingga soal tidak bisa ditambah lagi. Buat quiz baru atau hapus data pengerjaan.';
$string['err_zipmanysheets'] = 'ZIP berisi lebih dari satu file Excel. Masukkan tepat satu file Excel.';
$string['err_zipnosheet'] = 'ZIP tidak berisi file Excel.';
$string['importdone'] = '{$a->count} soal diimpor ke "{$a->quiz}". Total poin: {$a->sumgrades}. Nilai maksimum quiz: {$a->grade}.';
$string['importmore'] = 'Impor lagi';
$string['importparticipants'] = 'Daftarkan Peserta';
$string['importparticipants_intro'] = '<p>Unduh <a href="{$a->template}">template peserta</a>, isi satu baris per peserta, lalu unggah di sini. Kosongkan kolom <em>Password</em> agar dibuat otomatis. Peserta juga bisa <a href="{$a->manual}">ditambahkan satu per satu</a>.</p>';
$string['importquestions'] = 'Impor Soal';
$string['importquestions_intro'] = '<p>Unduh <a href="{$a->template}">template soal</a>. Unggah file Excel saja, atau – jika soal memakai gambar atau audio – file ZIP berisi file Excel dan semua file media.</p><p>Tidak ada yang disimpan sebelum Anda memeriksa pratinjau dan mengonfirmasi.</p>';
$string['manageadmins'] = 'Kelola Admin';
$string['maxgrade'] = 'Nilai maks.';
$string['moodlereport'] = 'Laporan detail Moodle';
$string['nocourseconfigured'] = 'Kursus lomba belum dikonfigurasi. Jalankan php public/local/planopolis/cli/setup.php';
$string['nofileuploaded'] = 'Tidak ada file yang diunggah.';
$string['noquizzes'] = 'Kursus belum memiliki quiz.';
$string['open'] = 'Buka';
$string['opens'] = 'Dibuka';
$string['panel'] = 'Panel Planopolis';
$string['participantfile'] = 'File peserta (Excel atau CSV)';
$string['participantfile_help'] = 'Kolom: Username, Password (opsional), Nama, Nama Belakang (opsional), Email (opsional), Instansi (opsional), Kota (opsional), Sesi (opsional – peserta dengan sesi yang sama dimasukkan ke grup yang sama).';
$string['participantlist'] = 'Daftar Peserta';
$string['participantsdone'] = '{$a} peserta terdaftar di kursus.';
$string['pcol_email'] = 'Email';
$string['pcol_firstname'] = 'Nama';
$string['pcol_group'] = 'Sesi';
$string['pcol_institution'] = 'Instansi';
$string['pcol_password'] = 'Password';
$string['pcol_username'] = 'Username';
$string['planopolis:bypassprotection'] = 'Tidak terkena proteksi salin di halaman quiz';
$string['planopolis:manage'] = 'Menggunakan Panel Planopolis';
$string['pluginname'] = 'Alat quiz Planopolis';
$string['previewquiz'] = 'Coba quiz';
$string['privacy:metadata:local_planopolis_log'] = 'Catatan kejadian saat mengerjakan quiz (meninggalkan halaman quiz, mencoba menyalin atau screenshot).';
$string['privacy:metadata:local_planopolis_log:attemptid'] = 'Pengerjaan quiz.';
$string['privacy:metadata:local_planopolis_log:eventtype'] = 'Jenis kejadian.';
$string['privacy:metadata:local_planopolis_log:timecreated'] = 'Waktu kejadian.';
$string['privacy:metadata:local_planopolis_log:userid'] = 'Peserta.';
$string['protect_blocked'] = 'Menyalin, mencetak, dan screenshot tidak diperbolehkan selama quiz.';
$string['protect_left'] = 'Anda meninggalkan halaman quiz. Kejadian ini dicatat. Klik di sini untuk melanjutkan.';
$string['pv_action'] = 'Tindakan';
$string['pv_autopassword'] = '(dibuat otomatis)';
$string['pv_enrolonly'] = 'Akun lama yang didaftarkan';
$string['pv_errors'] = 'Kesalahan';
$string['pv_file'] = 'File';
$string['pv_fixerrors'] = 'Perbaiki kesalahan berikut di file lalu unggah ulang. Belum ada yang disimpan.';
$string['pv_media'] = 'Media';
$string['pv_newusers'] = 'Akun baru';
$string['pv_options'] = 'Opsi';
$string['pv_preview'] = 'Pratinjau';
$string['pv_row'] = 'Baris';
$string['pv_totalmarks'] = 'Total poin';
$string['pv_updateusers'] = 'Akun yang diperbarui';
$string['pv_valid'] = 'Soal valid';
$string['pv_warnings'] = 'Peringatan';
$string['questionfile'] = 'File soal (Excel atau ZIP)';
$string['questionfile_help'] = 'Gunakan template soal. Untuk gambar atau audio, masukkan file Excel dan file media ke dalam satu ZIP, lalu tulis nama file di kolom Gambar Soal / Audio Soal, atau gunakan [img:file.png] di dalam soal atau opsi.';
$string['questions'] = 'Soal';
$string['quizzes'] = 'Daftar Quiz';
$string['res_blank'] = 'Kosong';
$string['res_correct'] = 'Benar';
$string['res_duration'] = 'Durasi';
$string['res_finished'] = 'Selesai';
$string['res_fullname'] = 'Nama';
$string['res_group'] = 'Sesi';
$string['res_inprogress'] = 'Sedang mengerjakan';
$string['res_institution'] = 'Instansi';
$string['res_leftpage'] = 'Keluar halaman';
$string['res_notstarted'] = 'Belum mulai';
$string['res_overdue'] = 'Lewat waktu';
$string['res_rank'] = 'Peringkat';
$string['res_score'] = 'Nilai';
$string['res_started'] = 'Mulai';
$string['res_status'] = 'Status';
$string['res_username'] = 'Username';
$string['res_wrong'] = 'Salah';
$string['results'] = 'Rekap Nilai';
$string['results_leftnote'] = '"Keluar halaman" menghitung berapa kali peserta berpindah dari jendela quiz (tab lain, aplikasi lain, atau snipping tool). Nilai sama diurutkan berdasarkan durasi tercepat.';
$string['results_summary'] = '{$a->finished} dari {$a->total} peserta sudah selesai. Nilai maksimum: {$a->max}.';
$string['rowlabel'] = 'Baris {$a->row} (No {$a->no})';
$string['runaudit'] = 'Jalankan Cek Quiz';
$string['set_courseid'] = 'ID kursus lomba';
$string['set_courseid_desc'] = 'Diisi otomatis oleh skrip setup.';
$string['set_protection'] = 'Proteksi halaman quiz';
$string['set_protection_desc'] = 'Blokir salin, cetak, dan tombol screenshot, sembunyikan soal saat peserta meninggalkan jendela, dan catat kejadiannya.';
$string['set_watermark'] = 'Watermark peserta';
$string['set_watermark_desc'] = 'Tampilkan nama dan username peserta samar-samar di halaman quiz agar foto yang bocor bisa dilacak.';
$string['setmaxgrade'] = 'Gunakan total poin sebagai nilai maksimum quiz';
$string['setmaxgrade_help'] = 'Jika dicentang, nilai akhir = jumlah poin (misalnya 40 soal × 4 = 160). Jika tidak, Moodle mengonversi nilai ke nilai maksimum quiz (misalnya 100).';
$string['sev_danger'] = 'Masalah';
$string['sev_success'] = 'OK';
$string['sev_warning'] = 'Peringatan';
$string['shuffledefault'] = 'Acak urutan opsi (kecuali baris berisi Acak Opsi = N)';
$string['status_created'] = 'Dibuat';
$string['status_enrolonly'] = 'Didaftarkan (password tidak berubah)';
$string['status_updated'] = 'Diperbarui, password baru';
$string['targetquiz'] = 'Quiz';
$string['timelimit'] = 'Batas waktu';
$string['unsupportedfiletype'] = 'Jenis file tidak didukung: {$a}';
$string['updateexisting'] = 'Perbarui akun yang sudah ada';
$string['updateexisting_help'] = 'Jika username sudah ada: dicentang = perbarui nama dan buat password baru; tidak dicentang = akun dibiarkan dan hanya didaftarkan ke kursus.';
$string['uploadagain'] = 'Unggah ulang';
$string['warn_alreadyinquiz'] = 'Baris {$a->row}: soal yang sama sudah ada sebagai soal nomor {$a->slot} di quiz ini.';
$string['warn_badshuffle'] = 'Acak Opsi "{$a}" tidak dikenali (gunakan Y atau N); memakai pengaturan default.';
$string['warn_duplicatemedia'] = 'ZIP berisi dua file bernama {$a}; yang terakhir dipakai.';
$string['warn_duplicatequestion'] = 'teks soal sama dengan baris {$a}.';
$string['warn_unusedmedia'] = 'File {$a} di ZIP tidak dipakai oleh soal mana pun.';
$string['warn_userexists'] = 'akun sudah ada; hanya akan didaftarkan ke kursus.';

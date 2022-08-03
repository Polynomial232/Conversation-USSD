<?php
    session_start();

    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    
    Class Conversation {
        
        Private $servername;
        Private $username;
        Private $password;
        Private $dbname;

        // input: none
        // output: koneksi database
        function __construct(){
            $this->uservername = "localhost";
            $this->username = "root";
            $this->password = "";
            $this->dbname = "flow";

            $this->db = new mysqli(
                $this->servername,
                $this->username,
                $this->password,
                $this->dbname
            );
        }

        function error($msg){
            echo '<div>';
            echo '<b>Error: </b>'.$msg;
            echo '</div>';
        }
        // input: tujuan nomor WA
        // output: query dari table `campaigns`
        Protected function getCampaignsAktif($getTujuan){
            return $this->db->query("SELECT * FROM `campaigns` WHERE `nomorWa`='$getTujuan' AND `aktif`='1'");
        }

        // input: none
        // output: data dari query `conversationsussd`
        Protected function point_one(){
            $conversationUSSD = $this->db->query("SELECT * FROM `conversationsussd` WHERE `dikirim`='0'");
            if($conversationUSSD->num_rows < 1){
                $this->error('Conversations USSD dikirim tidak 0');
                return NULL;
            }else{
                while($row = $conversationUSSD->fetch_object()){
                    $data[] = $row;
                }
                return $data;
            }
        }

        // input: nomor Whatsapp pengirim (unique) `conversationsussd`.`dari`
        // output: data dari query `conversationsussd`
        Protected function point_one_select($number){
            $conversationUSSD = $this->db->query("SELECT * FROM `conversationsussd` WHERE `dikirim`='0' AND `dari`='$number'");   
            if($conversationUSSD->num_rows < 1){
                $this->error('Conversations USSD dikirim tidak 0');
                return NULL;
            }else{
                while($row = $conversationUSSD->fetch_object()){
                    $data[] = $row;
                }
                return $data;
            }
        }

        // input: none
        // output: loop semua data dari point_one() 
        function point_two(){
            $datas = $this->point_one();
            foreach($datas as $data){
                echo '
                <div>
                    team_id: '.$data->team_id.'<br>
                    dari: '.$data->dari.' - unique<br>
                    tujuan: '.$data->tujuan.'<br>
                    tanggal: '.$data->tanggal.'<br>
                    pesan: '.$data->textPesan.'<br>
                    pages akhir: '.$data->id_pages_terakhir.'<br>
                    dikirim: '.$data->dikirim.'
                </div>';
            }
        }

        // input: none
        // output: mengecek apakah nomor Whatsapp tujuan ada dalam database dan aktif atau tidak
        function point_two_one(){
            $datas = $this->point_one();
            foreach($datas as $data){
                echo '<hr>';
                echo 'dari: '.$data->dari.' - unique';
                echo '<br> tujuan: '.$data->tujuan;
                $cekAktif = $this->getCampaignsAktif($data->tujuan);
                if($cekAktif->num_rows < 1){
                    $this->error('Data diatas tidak ada didalam table campaigns atau tidak aktif');
                }else{
                    echo '<br> Data diatas ada didalam table campaigns dan aktif';
                }
            }
        }

        // input: none
        // proses: mengecek `conversationussd`.`id_pages_terakhir` apakah 0 atau tidak
        function point_three(){
            $datas = $this->point_one();
            if($datas != NULL){
                foreach($datas as $data){
                    $pagesTerakhir = $data->id_pages_terakhir;
                    echo '<div>';
                    echo 'dari: '.$data->dari.' - unique';
                    echo '<br>pages Terakhir: '.$pagesTerakhir;
                    if($pagesTerakhir == 0){
                        $this->point_three_one($data->dari);
                    }else{
                        $getArrayFlowPages = $this->point_three_two($pagesTerakhir); 
                        if($getArrayFlowPages != NULL){
                            $getOptionGroup = $getArrayFlowPages[0];
                            $getPageText = $getArrayFlowPages[1];
                            if($getOptionGroup != 'freetext'){
                                $this->point_three_two_one($data, $getOptionGroup);
                            }else{
                                $this->point_three_two_two($data, $pagesTerakhir, $getPageText);
                            }
                        }
                    }
                    echo '</div>';
                }
            }
        }

        // input: nomor Whatsapp pengirim (unique) `conversationsussd`.`dari`
        // output: page Text dengan `flow_pages`.`uuid`=sm_t0
        Protected function point_three_one($unique){
            $flowPages = $this->db->query("SELECT `pageText`,`uuid`,`id` FROM `flow_pages` WHERE `uuid`='sm_t0'");
            $result = $flowPages->fetch_object();
            $flowId = $result->id;

            echo '<br>page Text: '.$result->pageText;
            
            $this->db->query("UPDATE `conversationsussd` SET `id_pages_terakhir`='$flowId' WHERE `dari`='$unique'");
        }

        // input: pages Terakhir dari data yang diambil
        // output: option Grop dari table `flow_pages`
        Protected function point_three_two($pagesTerakhir){
            $flowPages = $this->db->query("SELECT `optionGroup`, `pageText` FROM `flow_pages` WHERE `id`='$pagesTerakhir'");
            if($flowPages->num_rows < 1){
                $this->error('Pages Terakhir tidak ada dalam table');
                return NULL;
            }else{
                $result = $flowPages->fetch_object();
                echo '<br>optionGroup: '.$result->optionGroup;
                return array($result->optionGroup, $result->pageText);
            }
        }

        // input: data dari point_one() dan option group
        // proses: mengecek apakah text pesan dari `conversationussd` sama dengan pilihan yang ada di `flow_options` atau tidak
        Protected function point_three_two_one($data, $getOptionGroup){
            $cekAktif = $this->getCampaignsAktif($data->tujuan);
            $campaignsId = $cekAktif->fetch_object()->id;

            $flowOptions = $this->db->query("SELECT * FROM `flow_options` WHERE `campaignId`='$campaignsId' AND `groupUUID`='$getOptionGroup'");
            $cek = 1;
            while($result = $flowOptions->fetch_object()){
                if($data->textPesan == $result->optionAnswer){
                    $this->pesan($data, $result, $campaignsId);
                    break;
                }
                if($cek == $flowOptions->num_rows){
                    $this->pesan($data, $result, $campaignsId);
                }
                $cek++;
            }
        }

        // input: data dari point_one()
        // output: isi pesan yang akan dikirim ke nomor pengirim (`conversationussd`.`dari`)
        Protected function point_three_two_two($data, $pagesTerakhir){
            echo '<br>Pesan: '.$data->textPesan;
            $pesan = $data->textPesan;

            $policyStatus = $this->db->query("SELECT `current_status` FROM `policy_status` WHERE `policy_number`='$pesan'") or die();
            if($policyStatus->num_rows < 1){
                $this->error('Current Status tidak ditemukan');
            }else{
                $result = $policyStatus->fetch_object();
                echo '<br>Kirim Pesan Ke: '.$data->dari.'<br>Pesan Selanjutnya: '.$result->current_status;
                $unique = $data->dari;

                // matikan komentar dibawah untuk update table
                $this->db->query("UPDATE `conversationsussd` SET `dikirim`='1', `id_pages_terakhir`='$pagesTerakhir' WHERE `dari`='$unique'");
            }
        
        }

        // input: nomor Whatsapp pengirim (unique) yang dikirim
        // output: mengecek pesan apa yang akan dikirim ke pengirim
        // note: tidak jauh beda dengan point_three namun dapat dipilih salah satu nomor

        function point_three_once($number){
            $datas = $this->point_one_select($number);
            if($datas != NULL){
                echo '<div>';
                foreach($datas as $data){
                    $pagesTerakhir = $data->id_pages_terakhir;
                    echo 'dari: '.$data->dari.' - unique';
                    echo '<br>pages Terakhir:'.$pagesTerakhir;
                    if($pagesTerakhir == 0){
                        $this->point_three_one($data->dari);
                    }else{
                        $getArrayFlowPages = $this->point_three_two($pagesTerakhir); 
                        if($getArrayFlowPages != NULL){
                            $getOptionGroup = $getArrayFlowPages[0];
                            $getPageText = $getArrayFlowPages[1];
                            if($getOptionGroup != 'freetext'){
                                $this->point_three_two_one($data, $getOptionGroup);
                            }else{
                                $this->point_three_two_two($data, $pagesTerakhir, $getPageText);
                            }
                        }
                    }
                }
                echo '</div>';
            }
        }

        // input: data dari point_one(), result dari perulangan `flow_options` dan `campaigns`.`id`
        // output: pesan selanjutnya yang ditampilkan
        Protected function pesan($data, $result, $campaignsId){
            echo '<br>Pesan: '.$data->textPesan.' '.$result->optionRule.' '.$result->optionAnswer.' Goto: '.$result->optionGoto;
            $getGoto = $result->optionGoto;
            $pageText = $this->db->query("SELECT `pageText`,`id` FROM `flow_pages` WHERE `campaignId`='$campaignsId' AND `uuid`='$getGoto'");
            $result = $pageText->fetch_object();
            echo '<br>Pesan Selanjutnya: '.$result->pageText;

            $unique = $data->dari;
            $id = $result->id;

            // matikan komentar dibawah untuk update table
            $this->db->query("UPDATE `conversationsussd` SET `dikirim`='1', `id_pages_terakhir`='$id' WHERE `dari`='$unique'");
        }
    }

    // input: none
    // output: testing mengubah semua data pada `conversationsussd`.`dikirim` = '0'
    $db = new mysqli("localhost", "root", "", "flow");

    function set_all_zero(){
        $db = new mysqli("localhost", "root", "", "flow");
        $db->query("UPDATE `conversationsussd` SET `dikirim`='0'");
    }

    $conver = new Conversation();
    set_all_zero()
?>

<style>
    .flex{
        display: flex;
        flex-wrap: wrap;
        justify-content: center;
    }
    .flex > div{
        width: 20%;
        border: 1px solid black;
        padding: 10px;
        margin: 5px;
    }
</style>

<h2 align=center>Point Ke 1 & 2</h2>
<div class="flex">
    <?php $conver->point_two(); ?>
</div>

<h2 align=center>Point Ke 3</h2>

<div class="flex">
    <?php 
        // set ulang textPesan menjadi 2
        $db->query("UPDATE `conversationsussd` SET `textPesan`='2' WHERE  `textPesan`='1234567890'");
        $conver->point_three();
    ?>
</div>


<h2 align=center>Testing Salah Satu Nomor Pengirim Untuk Point Ke 3</h2>

<div class="flex">
    <?php
        // set ulang dikirim menjadi 0 semua
        set_all_zero();
        $conver->point_three_once(6281514092500); 
    ?>
</div>

<h2 align=center>Testing (current_status)</h2>

<div class="flex">
    <?php
        $nomor = 6281234870576;
        // set ulang textPesan menjadi 1234567890 (policy_number)
        $db->query("UPDATE `conversationsussd` SET `textPesan`='1234567890' WHERE `dari`='$nomor'");
        $conver->point_three_once($nomor);
    ?>
</div>

<h2 align=center>Apabila ConversationsUSSD dikirim = 0</h2>

<div class="flex">
    <?php $conver->point_three_once($nomor); ?>
</div>
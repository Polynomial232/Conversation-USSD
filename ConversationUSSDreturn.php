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

        // input: text error (String)
        // output: error message
        function error($msg){
            return '<b style="color: red">Error:</b> '.$msg;
        }

        // input: nomorWA `dari`
        // output: query ke untuk select table
        Protected function queryConversationussd($dari){
            if($dari == NULL){
                return $conversationUSSD = $this->db->query("SELECT * FROM `conversationsussd` WHERE `dikirim`='0'");
            }else{
                return $conversationUSSD = $this->db->query("SELECT * FROM `conversationsussd` WHERE `dikirim`='0' AND `dari`='$dari'");
            }
        }

        // input: nomorWA `dari` bisa kosong juga
        // output: (object array) table `conversationsussd`
        function getConversationussd($dari = NULL){
            $query = $this->queryConversationussd($dari);
            
            if($query->num_rows < 1){
                return $this->error('Conversations USSD dikirim tidak 0');
            }else{
                while($row = $query->fetch_object()){
                    $queryCampigns = $this->getCampaignsAktif($row->tujuan);
                    if($queryCampigns->num_rows < 1){
                        $data[] = NULL;
                    }else{
                        $data[] = $row;
                    }
                }
                return $data;
            }
        }

        // input: nomorWA `dari`
        // output: Pesan Selanjutnya berupa `flow_pages`.`pageText` atau `policy_status`.`current_status` yang akan dikirimkan ke pengirim
        function getNext($dari){
            $datas = $this->getConversationussd($dari);
            foreach($datas as $data){
                $pagesTerakhir = $data->id_pages_terakhir;
                if($pagesTerakhir == 0){
                    $flowPages = $this->db->query("SELECT `pageText`,`uuid`,`id` FROM `flow_pages` WHERE `uuid`='sm_t0'");
                    $result = $flowPages->fetch_object();
                    $flowId = $result->id;
                    $unique = $data->dari;
                    
                    $this->db->query("UPDATE `conversationsussd` SET `id_pages_terakhir`='$flowId' WHERE `dari`='$unique'");
                    
                    return $result->pageText;
                }else{
                    $flowPages = $this->db->query("SELECT `optionGroup`, `pageText`, `id` FROM `flow_pages` WHERE `id`='$pagesTerakhir'");
                    if($flowPages->num_rows < 1){
                        return $this->error('Pages Terakhir tidak ada dalam table');
                    }else{
                        $result = $flowPages->fetch_object();
                        $getOptionGroup = $result->optionGroup;
                        $getPageText = $result->pageText;
                        $getCampaigns = $this->getCampaignsAktif($data->tujuan);
                        $resultCampaigns = $getCampaigns->fetch_object();
                        $campaignId = $resultCampaigns->id;

                        if($getOptionGroup != 'freetext'){
                            $flowOptions = $this->db->query("SELECT * FROM `flow_options` WHERE `campaignId`='$campaignId' AND `groupUUID`='$getOptionGroup'");
                            $cek = 1;
                            while($result = $flowOptions->fetch_object()){
                                if($data->textPesan == $result->optionAnswer or $cek == $flowOptions->num_rows){
                                    $getGoto = $result->optionGoto;
                                    $pageText = $this->db->query("SELECT `pageText`,`id` FROM `flow_pages` WHERE `campaignId`='$campaignId' AND `uuid`='$getGoto'");
                                    $result = $pageText->fetch_object();
                                    $unique = $data->dari;
                                    $id = $result->id;
                                    
                                    // matikan komentar dibawah untuk update table
                                    $this->db->query("UPDATE `conversationsussd` SET `dikirim`='1', `id_pages_terakhir`='$id' WHERE `dari`='$unique'");

                                    return $result->pageText;
                                    break;
                                }
                                $cek++;
                            }

                        }else{
                            $pesan = $data->textPesan;
                            $policyStatus = $this->db->query("SELECT `current_status` FROM `policy_status` WHERE `policy_number`='$pesan'");
                            if($policyStatus->num_rows < 1){
                                return $this->error('Current Status tidak ditemukan');
                            }else{
                                $result = $policyStatus->fetch_object();
                                $unique = $data->dari;
                                
                                // matikan komentar dibawah untuk update table
                                $this->db->query("UPDATE `conversationsussd` SET `dikirim`='1', `id_pages_terakhir`='$pagesTerakhir' WHERE `dari`='$unique'");
                                return $result->current_status;
                            }
                        }
                    }
                }
            }
        }

        Protected function getCampaignsAktif($nomorWA){
            return $this->db->query("SELECT * FROM `campaigns` WHERE `nomorWa`='$nomorWA' AND `aktif`='1'");
        }
    }

    $conver = new Conversation();
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

<div class="flex">
    <?php 
        $datas = $conver->getConversationussd();
        
        if(is_array($datas)){
            foreach($datas as $data){
                echo '<div>';
                if($data == NULL){
                    echo $conver->error('Data diatas tidak ada didalam table campaigns atau tidak aktif');
                }else{
                    echo 'team_id: '.$data->team_id.'<br>';
                    echo 'dari: '.$data->dari.' - unique<br>';
                    echo 'tujuan: '.$data->tujuan.'<br>';
                    echo 'tanggal: '.$data->tanggal.'<br>';
                    echo 'pesan: '.$data->textPesan.'<br>';
                    echo 'pages akhir: '.$data->id_pages_terakhir.'<br>';
                    echo 'dikirim: '.$data->dikirim.'<br>';
                    echo 'Pesan Selanjutnya: '.$conver->getNext($data->dari);
                }
                echo '</div>';
            }
        }else{
            echo $datas;
        }
    ?>
</div>
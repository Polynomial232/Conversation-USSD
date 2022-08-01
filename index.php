<?php
    session_start();

    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $db = new mysqli("localhost", "root", "", "flow");

    $conversationUSSD = $db->query("SELECT * FROM `conversationsussd` WHERE `dikirim`='0'");
    echo 'Conversation USDD Tujuan : ';
    $no = 1;
    if($conversationUSSD->num_rows == 0){
        set_all0($db);
    }
    while($conversationData = $conversationUSSD->fetch_object()){
        echo '<hr>';
        echo 'team_id:'.$conversationData->team_id;
        echo '<br>';
        echo 'dari:'.$conversationData->dari;
        echo '<br>';
        echo 'tujuan:'.$conversationData->tujuan;
        echo '<br>';
        echo 'tanggal:'.$conversationData->tanggal;
        echo '<br>';
        echo 'pesan:'.$conversationData->textPesan;
        echo '<br>';
        echo 'pages akhir:'.$conversationData->id_pages_terakhir;
        echo '<br>';
        echo 'dikirim:'.$conversationData->dikirim;
        echo '<br>';

        $tujuan = $conversationData->tujuan;
        $campaigns = $db->query("SELECT * FROM `campaigns` WHERE `nomorWA`='$tujuan' AND `aktif`='1'");

        if($campaigns->num_rows != 0){
            $terakhir = $conversationData->id_pages_terakhir;
            $unique = $conversationData->dari;
            echo 'id_pages_terakhir: '.$terakhir;
            echo '<br>';
            if($terakhir == 0){
                $flow_pages = $db->query("SELECT `pageText`, `id` FROM `flow_pages` WHERE `uuid`='sm_t0'");
                while($flow_pagesData = $flow_pages->fetch_object()){
                    echo $flow_pagesData->pageText;
                    $flow_id = $flow_pagesData->id;
                    $db->query("UPDATE `conversationsussd` SET `id_pages_terakhir`='$flow_id' WHERE `dari`='$unique'");
                }
            }else{
                $flow_pages = $db->query("SELECT `optionGroup` FROM `flow_pages` WHERE `id`='$terakhir'");
                while($flow_pagesData = $flow_pages->fetch_object()){
                    $getOption = $flow_pagesData->optionGroup;
                    echo 'optionGroup: '.$getOption;

                    if($getOption != 'freetext'){
                        $flow_options = $db->query("SELECT * FROM `flow_options` WHERE `groupUUID`='$getOption'");
                        while($flow_optionsData = $flow_options->fetch_object()){
                            if($conversationData->textPesan == $flow_optionsData->optionAnswer){
                                $goto = $flow_optionsData->optionGoto;
                                break;
                            }
                            $goto = $flow_optionsData->optionGoto;
                            echo '<br>';
                            echo 'Option Text:'.$flow_optionsData->optionText;
                        }
                        echo '<br>';
                        echo 'Pesan: '.$conversationData->textPesan;
                        echo '<br>';
                        echo 'optionGoto: '.$goto;
                        $pesanLanjut = $db->query("SELECT `pageText`, `id` FROM `flow_pages` WHERE `uuid`='$goto'");
                        while($pesanLanjutData = $pesanLanjut->fetch_object()){
                            echo '<br>';
                            echo $pesanLanjutData->pageText;
                            $id = $pesanLanjutData->id;
                            $setDikirim = $db->query("UPDATE `conversationsussd` SET `dikirim`='1', `id_pages_terakhir`='$id' WHERE `dari`='$unique'");
                        }
                    }else{
                        $pesan = $conversationData->textPesan;
                        $policy_status = $db->query("SELECT `current_status` FROM `policy_status` WHERE `policy_number`='$pesan'");
                        while($policyData = $policy_status->fetch_object()){
                            echo '<br>';
                            echo 'Kirim Pesan Ke:'.$conversationData->dari.'<br>Isi nya: '.$policyData->current_status;
                            $setDikirim = $db->query("UPDATE `conversationsussd` SET `dikirim`='1', `id_pages_terakhir`='0' WHERE `dari`='$unique'");
                        }
                    }
                }
            }
        }
    }

    function set_all0($db){
        $db->query("UPDATE `conversationsussd` SET `dikirim`='0'");
    }
?>
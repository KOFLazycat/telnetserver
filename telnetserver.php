<?php
	
//a little bit of fun with a socket server

error_reporting(E_ALL);

/* Allow the script to hang around waiting for connections. */
set_time_limit(0);

/* Turn on implicit output flushing so we see what we're getting
 * as it comes in. */
ob_implicit_flush();

$address = '0.0.0.0';
$port = 8765;
// 限制输入命令
$command_list = array("mul", "incr", "div", "conv_tree");

// 命令执行函数
function bufExec($command, $args) : string {
    $res = "0";
    switch ($command) {
        case 'mul':
            $res = intval($args[0]) * intval($args[1]);
            break;
        case 'incr':
            $res = intval($args[0]) + 1;
            break;
        case 'div':
            if (intval($args[1]) != 0) {
                $res = intval($args[0]) / intval($args[1]);
            } else {
                $res = "--";
            }
            break;
        case 'conv_tree':
            $res = convTree();
            break;
    }
    $res = strval($res);
    return $res;
}

// json数据格式转换
function convTree() : string {
    $json_test = '[{"id":200002538,"name":"空心菜类","level":3,"namePath":"蔬菜/豆制品,叶菜类,空心菜类"},{"id":200002537,"name":"香菜类","level":3,"namePath":"蔬菜/豆制品,葱姜蒜椒/调味菜,香菜类"},{"id":200002536,"name":"紫苏/苏子叶","level":3,"namePath":"蔬菜/豆制品,叶菜类,紫苏/苏子叶"},{"id":200002543,"name":"乌塌菜/塌菜/乌菜","level":3,"namePath":"蔬菜/豆制品,叶菜类,乌塌菜/塌菜/乌菜"},{"id":200002542,"name":"菜心/菜苔类","level":3,"namePath":"蔬菜/豆制品,叶菜类,菜心/菜苔类"},{"id":200002540,"name":"马兰头/马兰/红梗菜","level":3,"namePath":"蔬菜/豆制品,叶菜类,马兰头/马兰/红梗菜"},{"id":200002531,"name":"苋菜类","level":3,"namePath":"蔬菜/豆制品,叶菜类,苋菜类"},{"id":200002528,"name":"其他叶菜类","level":3,"namePath":"蔬菜/豆制品,叶菜类,其他叶菜类"}]';
    $res = "";
    $res_map = array();
    $json_arr = json_decode($json_test, true);

    foreach ($json_arr as $v) {
        $name_path_arr = explode(',', $v['namePath']);
        $lv1_id = md5($name_path_arr[0]);
        $lv2_id = md5($name_path_arr[1]);
        $lv3_id = md5($v["id"]);

        // lv1
        if (empty($res_map[$lv1_id])) {
            $res_map[$lv1_id]["id"] = $lv1_id;
            $res_map[$lv1_id]["id_path"] = ",{$lv1_id},";
            $res_map[$lv1_id]["is_leaf"] = 2;
            $res_map[$lv1_id]["level"] = 1;
            $res_map[$lv1_id]["name"] = $name_path_arr[0];
            $res_map[$lv1_id]["name_path"] = "{$name_path_arr[0]}";
            $res_map[$lv1_id]["parent_id"] = 0;
            $res_map[$lv1_id]["children"] = array();
        }

        // lv2
        if (empty($res_map[$lv1_id]["children"][$lv2_id])) {
            $res_map[$lv1_id]["children"][$lv2_id]["id"] = $lv2_id;
            $res_map[$lv1_id]["children"][$lv2_id]["id_path"] = ",{$lv1_id},{$lv2_id},";
            $res_map[$lv1_id]["children"][$lv2_id]["is_leaf"] = 2;
            $res_map[$lv1_id]["children"][$lv2_id]["level"] = 2;
            $res_map[$lv1_id]["children"][$lv2_id]["name"] = $name_path_arr[1];
            $res_map[$lv1_id]["children"][$lv2_id]["name_path"] = "{$name_path_arr[0]},{$name_path_arr[1]}";
            $res_map[$lv1_id]["children"][$lv2_id]["parent_id"] = $lv1_id;
            $res_map[$lv1_id]["children"][$lv2_id]["children"] = array();
        }

        // lv3
        $lv3_one = array();
        $lv3_one["id"] = $lv3_id;
        $lv3_one["id_path"] = ",{$lv1_id},{$lv2_id},{$lv3_id},";
        $lv3_one["is_leaf"] = 1;
        $lv3_one["level"] = 3;
        $lv3_one["name"] = $v["name"];
        $lv3_one["name_path"] = $v['namePath'];
        $lv3_one["parent_id"] = $lv2_id;
        $res_map[$lv1_id]["children"][$lv2_id]["children"][] = $lv3_one;
    }

    // 调整输出格式
    foreach ($res_map as &$v) {
        $v["children"] = array_values($v["children"]);
    }
    $res_map = array_values($res_map);
    $res = json_encode($res_map, JSON_UNESCAPED_UNICODE);
    return $res;
}


if (($sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP)) === false) {
    echo "socket_create() failed: reason: " . socket_strerror(socket_last_error()) . "\n";
    die();
}

if (socket_bind($sock, $address, $port) === false) {
    echo "socket_bind() failed: reason: " . socket_strerror(socket_last_error($sock)) . "\n";
    die();
}

if (socket_listen($sock, 5) === false) {
    echo "socket_listen() failed: reason: " . socket_strerror(socket_last_error($sock)) . "\n";
    die();
}


//clients array
$clients = array();

do {
    
    $read = array();
    $read[] = $sock;
    
    $read = array_merge($read,$clients);
    
    $write = NULL;
    $except = NULL;
    
    // Set up a blocking call to socket_select
    if(socket_select($read,$write, $except, $tv_sec = 5) < 1)
    {
        //    SocketServer::debug("Problem blocking socket_select?");
        continue;
    }
    
    // Handle new Connections
    if (in_array($sock, $read)) {   
    
	    if (($msgsock = socket_accept($sock)) === false) {
	        echo "socket_accept() failed: reason: " . socket_strerror(socket_last_error($sock)) . "\n";
	        break;
	    }
	    
	    $clients[] = $msgsock;
        $key = array_keys($clients, $msgsock);
    
		$msg="Connected\n";
    
		socket_write($msgsock, $msg, strlen($msg));
    
		/* Send instructions. */
		$msg = "\nWelcome to the PHP Test Server. \n" .
        	"To quit, type 'quit'. To shut down the server type 'shutdown'.\n";
		socket_write($msgsock, $msg, strlen($msg));
	}
	
	
	foreach ($clients as $key => $client) { // for each client        
        if (in_array($client, $read)) {
            if (false === ($buf = socket_read($client, 2048, PHP_NORMAL_READ))) {
                echo "socket_read() falló: razón: " . socket_strerror(socket_last_error($client)) . "\n";
                break 2;
            }
            if (!$buf = trim($buf)) {
                continue;
            }
            if ($buf == 'quit') {
                unset($clients[$key]);
                socket_close($client);
                break;
            }
            if ($buf == 'shutdown') {
                socket_close($client);
                break 2;
            }

            // 命令拆分识别
            $buf_arr = explode(" ", $buf);
            $buf_command = $buf_arr[0];
            $talkback = "{$buf} result is: \n";
            $res = "";
            if (in_array($buf_command, $command_list)) {
                $res = bufExec($buf_command, array_slice($buf_arr, 1));
            }
            // 数据回传
            $talkback = $talkback . $res . "\n";
            
            foreach ($clients as $sub_key => $sub_client)
            {
	            socket_write($sub_client, $talkback, strlen($talkback));
	        }
	        
            echo "$buf\n";
        }
        
    } 
	
} while (true);

socket_close($sock);

?>
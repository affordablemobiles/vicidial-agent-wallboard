<?php

if (@$_POST['data']){
	file_put_contents('data.json', $_POST['data']);
	echo "200 OK";
} else {
	echo "500 Fail";
}

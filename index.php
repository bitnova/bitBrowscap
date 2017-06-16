<?php
	use phpbrowscap\Browscap;

	$agent_type = 'default';
	$agent_string = null;
	$method = null;
	if (isset($_REQUEST['agent_type'])) $agent_type = $_REQUEST['agent_type'];
	if (isset($_REQUEST['agent_string'])) $agent_string = $_REQUEST['agent_string'];
	if (isset($_REQUEST['browscap_method'])) $method = $_REQUEST['browscap_method'];
	
	$browser_info = null;
	$t0 = microtime(true);
	switch ($method)
	{
		case 'Test bitBrowscap':
			require_once 'bitBrowscap.php';
			
			$browser_info = TbitBrowscap::instance()->getBrowser(($agent_type == 'default' ? null : $agent_string), true);			
			
			break;
			
		case 'Test Browscap 2':
			require_once 'browscap.php';
				
			if (!is_dir('browscap_cache')) mkdir('browscap_cache');
			$browser = new Browscap('browscap_cache');
			$browser->localFile = 'php_browscap.ini';
			$browser->doAutoUpdate = false;
			$browser_info = $browser->getBrowser(($agent_type == 'default' ? null : $agent_string), true);
			break;
	}
	$t1 = microtime(true);
	echo '<span>Browser identification made in <strong>'.(($t1 - $t0) * 1000).' msec</strong>.</span><br/>';
	var_dump($browser_info);
?>

<form action="index.php" method="get">
	<table>
		<tr>
			<td><input type="radio" name="agent_type" value="default" <?php if ($agent_type == 'default') echo 'checked="checked"'; ?>></input></td>
			<td><label>User Agent:</label></td><td><?php echo $_SERVER['HTTP_USER_AGENT'];?></td>
		</tr>
		<tr>
			<td><input type="radio" name="agent_type" value="custom" <?php if ($agent_type != 'default') echo 'checked="checked"'; ?>></input></td>
			<td><label>Custom Agent:</label></td><td><input type="text" size="50" name="agent_string" value="<?php echo $agent_string;?>"></input></td>
		</tr>
		<tr>
			<td colspan="3"><hr/></td>
		</tr>
		<tr>
			<td></td><td><input type="submit" name="browscap_method" value="Test bitBrowscap"></input></td><td><input type="submit" name="browscap_method" value="Test Browscap 2"></input></td>
		</tr>
	</table>
</form>

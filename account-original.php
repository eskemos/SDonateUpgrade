<?php

	$currentPage = "account";

	require 'steamauth/steamauth.php';

	$_SESSION['returnurl'] = 'account.php';

	require_once('config.php');

	try {
		$dbcon = new PDO("mysql:host=$dbhost;dbname=$dbname", $dbusername, $dbpassword);
		$dbcon->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
	} catch(PDOException $e){
		echo 'MySQL Error:' . $e->getMessage();
	exit();
	}

	$sql = $dbcon->prepare("SELECT value FROM settings WHERE setting='paypalbutton'");
	$sql->execute();
	$result = $sql->fetch(PDO::FETCH_ASSOC);
	$buttonType = $result['value'];

	if(isset($_SESSION['username'])){
		$sql = $dbcon->prepare("SELECT * FROM users WHERE username=:username");
		$values = array(':username' => $_SESSION['username']);
		$sql->execute($values);
		$results = $sql->fetchAll(PDO::FETCH_ASSOC);
		$userID = $results[0]['id'];

		$avatar = '';
		$steamid = FALSE;

		if(empty($results[0]['avatar'])){
			$avatar = 'img/defavatar.jpg';
		} else {
			$avatar = $results[0]['avatar'];
		}

		if(!empty($results[0]['steamid'])){
			$steamid = $results[0]['steamid'];
			$linkedsteaminfo = '<a class="underlined-link" target="_blank" href=http://steamcommunity.com/profiles/' . $steamid . '>' . $steamid . '</a><br>';
		} else {
			$linkedsteaminfo = 'You do not have a Steam ID linked to your account, click below to link one: ';
		}

		if(!empty($results[0]['email'])){
			$emailinfo = htmlspecialchars($results[0]['email'], ENT_QUOTES | ENT_HTML5) . '<button type="button" class="submit-button" onclick="addEmail();">Change Email</button>';
		} else {
			$emailinfo = 'You do not have an email address linked to your account, click below to link one:
				<button type="button" class="submit-button" onclick="addEmail();">Add Email</button>';
		}

		$sql = $dbcon->prepare("SELECT * FROM transactions WHERE purchaser=:username ORDER BY time DESC");
		$values = array(':username' => $_SESSION['username']);
		$sql->execute($values);
		$results1 = $sql->fetchAll(PDO::FETCH_ASSOC);
		array_walk_recursive($results1, "escapeHTML");

		$totalpurchases = 0;
		$purchasevalue = 0;

		foreach ($results1 as $key => $value) {
			$totalpurchases++;
			$purchasevalue = $purchasevalue + $results1[$key]['value'];
		}

		$resultsJS = json_encode($results1);

		$sql = $dbcon->prepare("SELECT * FROM settings WHERE setting='paypalenabled'");
		$sql->execute();
		$result = $sql->fetchAll(PDO::FETCH_ASSOC);
		$paypalEnabled = $result[0]['value'];

		$sql = $dbcon->prepare("SELECT * FROM settings WHERE setting='starpassenabled'");
		$sql->execute();
		$result = $sql->fetchAll(PDO::FETCH_ASSOC);
		$starpassEnabled = $result[0]['value'];

		$sql = $dbcon->prepare("SELECT * FROM settings WHERE setting='creditsenabled'");
		$sql->execute();
		$result = $sql->fetchAll(PDO::FETCH_ASSOC);
		$creditsEnabled = $result[0]['value'];

		$sql = $dbcon->prepare("SELECT * FROM settings WHERE setting='paypalsandbox'");
		$sql->execute();
		$result = $sql->fetchAll(PDO::FETCH_ASSOC);
		$paypalSandbox = $result[0]['value'];

		$sql = $dbcon->prepare("SELECT * FROM settings WHERE setting='paypalemail'");
		$sql->execute();
		$result = $sql->fetchAll(PDO::FETCH_ASSOC);
		$paypalEmail = $result[0]['value'];

		if($paypalSandbox == "1"){
			$paypalURL = "https://www.sandbox.paypal.com/cgi-bin/webscr";
		} else {
			$paypalURL = "https://www.paypal.com/cgi-bin/webscr";
		}

		if($starpassEnabled == "1" AND isset($_GET['starpasssuccess'])){
			$sql = $dbcon->prepare("SELECT * FROM settings WHERE setting='starpasscode'");
			$sql->execute();
			$result = $sql->fetchAll(PDO::FETCH_ASSOC);
			$starpassCode = $result[0]['value'];
			$pubID = intval(get_string_between($starpassCode, 'error_code2.php?idd=', '&idp='));
			$privID = intval(get_string_between($starpassCode, '&idp=', '"></noscript><script type="text/javascript"'));
			// instantiation of variables
			$ident=$idp=$ids=$idd=$codes=$code1=$code2=$code3=$code4=$code5=$datas='';
			$idp = $privID;
			// $ids is no longer used, but please continue its use for compatibility reasons;
			$idd = $pubID;
			$ident=$idp.";".$ids.";".$idd;
			// We recieve the code(s) under the form 'xxxxxxxx;xxxxxxxx'
			if(isset($_POST['code1'])) $code1 = $_POST['code1'];
			if(isset($_POST['code2'])) $code2 = ";".$_POST['code2'];
			if(isset($_POST['code3'])) $code3 = ";".$_POST['code3'];
			if(isset($_POST['code4'])) $code4 = ";".$_POST['code4'];
			if(isset($_POST['code5'])) $code5 = ";".$_POST['code5'];
			$codes=$code1.$code2.$code3.$code4.$code5;
			// We receive input from the DATAS field
			if(isset($_POST['DATAS'])) $datas = $_POST['DATAS'];
			// We encode the three strings in URL
			$ident=urlencode($ident);
			$codes=urlencode($codes);
			$datas=urlencode($datas);

			/* Request sent to the StarPass server
			In the variable tab [0], we receive the server response.
			In the variable tab [1], we receive the access URL or the error message coming from the server. */
			$get_f=@file( "http://script.starpass.fr/check_php.php?ident=" . $ident . "&codes=" . $codes . "&DATAS=" . $datas );
			if(!$get_f)
			{
			exit( "You server does not have access to the StarPass server. Please contact your website host. " );
			}
			$tab = explode("|",$get_f[0]);

			if(!$tab[1]) $url = "http://script.starpass.fr/error.php";
			else $url = $tab[1];

			// in $pays, we have the countries having the offer. example "fr"
			$pays = $tab[2];
			// in $palier we have the rate of the offer. example "Plus A"
			$palier = urldecode($tab[3]);
			// in $id_palier we have the offer identifier
			$id_palier = urldecode($tab[4]);
			// in $type we have the offer type. example "sms", "prc, "cb", etc.
			$type = urldecode($tab[5]);
			// At any moment, you may check the rate list at; the address : http://script.starpass.fr/palier.php

			// if $tab[0] doesn't answer "YES" then the access is refused;
			// We redirect to a URL error page
			if( substr($tab[0],0,3) != "OUI" )
			{
			       header( "Location: $url" );
			       exit;
			}
			else
			{
			       $credit = $results[0]['credit'];
				   $credit += 3.00;
				   $sql = $dbcon->prepare("UPDATE users SET credit=:credit WHERE id=:id");
				   $values = array(':credit' => $credit, ':id' => $results[0]['id']);
				   $sql->execute($values);
				   $codeAdded = true;
				   $results[0]['credit'] = $credit;
				   $sql = $dbcon->prepare("INSERT INTO transactions(purchaser, usernametype, username, game, expires, expiretime, endcommands, transactionid, package, packageid, paymentmethod, value, status, params) VALUES(:purchaser, :usernametype, :username, :game, :expires, NOW(), :endcommands, :transactionid, :package, :packageid, :paymentmethod, :value, :status, :params)");
				   $values = array(':purchaser' => $_SESSION['username'], ':usernametype' => "", ':username' => "", ':game' => "StarPass Credit Purchase", ':expires' => 0, ':endcommands' => "[]", ':transactionid' => 'StarPass Credit Purchase', ':package' => 'StarPass Credit Purchase', ':packageid' => -1, ':paymentmethod' => "StarPass", ':value' => '3.00', ':status' => 'complete', ':params' => '[]');
				   $sql->execute($values);
			}
		}
	}
?>

<!DOCTYPE html>

<html>

	<head>
		<link rel="stylesheet" href="css/bootstrap.min.css">
		<link rel="stylesheet" href="css/style.php">
		<link href='font/fonts.css' rel='stylesheet' type='text/css'>
		<script src="js/jquery.js"></script>
		<script type="text/javascript" src="js/jqueryrotate.js"></script>
		<meta charset="utf-8"/>
		<title>SDonate Donation System</title>
	</head>

	<body>

		<?php require('components/topnavbar.php'); ?>

		<div id="content-container">
			<ul id="hidden-list">
				<a href="index.php"><li class="hidden-list-button"><span class="glyphicon glyphicon-home" style="margin-right: 10px;"></span><?= getLangString("home"); ?></li></a>
				<a href="packages.php"><li class="hidden-list-button"><span class="glyphicon glyphicon-shopping-cart" style="margin-right: 10px;"></span><?= getLangString("store"); ?></li></a>

				<?php
					if(isset($_SESSION['admin'])){
						if($_SESSION['admin'] === true){
							print('<a href="dashboard.php"><li class="hidden-list-button"><span class="glyphicon glyphicon-cog" style="margin-right: 10px;"></span>Admin</li></a>');
						}
					}

					if(!isset($_SESSION['username'])) {
						print('<a href="login.php"><li class="hidden-list-button"><span class="glyphicon glyphicon-user" style="margin-right: 10px;"></span>' . getLangString("login") . '</li></a>');
					} else {
						print('<a href="account.php"><li class="hidden-list-button active"><span class="glyphicon glyphicon-user" style="margin-right: 10px;"></span>' . getLangString("account") . '</li></a>');
					}
				?>

			</ul>

			<?php

				if(!isset($_SESSION['username'])){
					print('
						<div id="must-login">
							' . getLangString("must-login") . ' <br><br>
						</div>

						<script src="js/bootstrap.js"></script>
						<script src="js/main.js"></script>
					');
					exit();
				}

			?>

			<div id="account-container" class="container-fluid">
				<div id="top-bar">
					<div id="left-buttons">
						<img id="steam-avatar" src=<?php echo '"' . $avatar . '"'; ?>>
						<div id="steam-username"><?php echo htmlspecialchars($results[0]['username'], ENT_QUOTES | ENT_HTML5); ?></div>
					</div>
					<div id="rightbuttons">
						<form action="logout.php">
							<button class="submit-button" type="submit" name="logout" style="display: inline-block; margin-bottom: 0; margin-top: 4px; float: right;"><?= getLangString("log-out"); ?></button>
						</form>
						<button class="submit-button" type="button" name="changepassword" style="display: inline-block; margin-bottom: 0; margin-top: 4px; margin-right: 20px; float: right;" onclick="changePassword();"><?= getLangString("change-password"); ?></button>
					</div>
				</div>
				<div class="row">
					<div id="account-info" class="col-md-12">
						<div class="statistics-box">
							<div class="statistics-title">Linked Accounts</div>
							<div class="statistics-content">
								Steam ID: <?php print($linkedsteaminfo); if(empty($results[0]['steamid'])){ print('<div id="steam-login-container-account">'); loginbutton('rectangle'); print('</div>'); } ?>
								Email Address: <?php print($emailinfo); ?>
							</div>
						</div>
						<?php
							if($starpassEnabled == "1" || $creditsEnabled == "1"){
								$starpassHTML = "";
								$paypalHTML = "";
								if($paypalEnabled == "1"){
									$paypalHTML = getLangString("add-credit") . '<a href="#" class="underlined-link" onclick="addPaypalCredit();">' . getLangString("click-to-add-credit-paypal") . '</a><br>';
								}
								if($starpassEnabled == "1"){
									$sql = $dbcon->prepare("SELECT * FROM settings WHERE setting='starpasscode'");
									$sql->execute();
									$result = $sql->fetchAll(PDO::FETCH_ASSOC);
									$starpassCode = $result[0]['value'];
									$pubID = get_string_between($starpassCode, 'error_code2.php?idd=', '&idp=');
									$starpassHTML = getLangString("add-€3-credit") . '<a href="starpass.php" target="_blank" class="underlined-link">' . getLangString("click-to-add-credit-starpass") . '</a><br>';
								}
								print('
									<div class="statistics-box" style="margin-top: 30px;">
										<div class="statistics-title">Credits</div>
										<div class="statistics-content">
											' . getLangString("credit") . $results[0]['credit'] . ' ' . $currencycode . '<br>' .
											$paypalHTML .
											$starpassHTML .
										'</div>
									</div>
								');
							}
						?>
						<div id="purchase-statistics" class="statistics-box">
							<div class="statistics-title"><?= getLangString("purchase-statistics"); ?></div>
							<div class="statistics-content">
								<?= getLangString("purchases") . ': '; ?><?php print($totalpurchases); ?> <br>
								<?= getLangString("purchase-value"); ?><?php print($currencysymbol . number_format((float)$purchasevalue, 2, '.', '')); ?> <br>
							</div>
						</div>
						<div id="purchase-list" class="statistics-box">
							<div class="statistics-title"><?= getLangString("purchases"); ?></div>
							<div class="statistics-content table-responsive">
								<table class="table">
									<thead>
										<tr>
											<th><?= getLangString("date"); ?><button type="button" class="btn btn-default btn-sm tooltip-btn" data-toggle="tooltip" data-placement="top" title="The date and time at which this transaction took place.">?</button></th>
											<th><?= getLangString("game"); ?><button type="button" class="btn btn-default btn-sm tooltip-btn" data-toggle="tooltip" data-placement="top" title="The game this package applies to.">?</button></th>
											<th><?= getLangString("package"); ?><button type="button" class="btn btn-default btn-sm tooltip-btn" data-toggle="tooltip" data-placement="top" title="The name of the package purchased.">?</button></th>
											<th><?= getLangString("value"); ?> (<?php print($currencycode); ?>)<button type="button" class="btn btn-default btn-sm tooltip-btn" data-toggle="tooltip" data-placement="top" title="The total value of this transaction.">?</button></th>
											<th><?= getLangString("status")?><button type="button" class="btn btn-default btn-sm tooltip-btn" data-toggle="tooltip" data-placement="top" title="The status of this purchase. Note that 'Complete' does not mean that the commands have executed.">?</button></th>
											<th><?= getLangString("info"); ?><button type="button" class="btn btn-default btn-sm tooltip-btn" data-toggle="tooltip" data-placement="top" title="View more information about this transaction.">?</button></th>
										</tr>
									</thead>
									<tbody>
										<?php

											if($totalpurchases > 0){
												foreach($results1 as $key => $value){
													print(
														'<tr>' .
														'<td>' . $results1[$key]['time'] . '</td>' .
														'<td>' . $results1[$key]['game'] . '</td>' .
														'<td>' . $results1[$key]['package'] . '</td>' .
														'<td>' . $currencysymbol . $results1[$key]['value'] . '</td>' .
														'<td>' . ucfirst($results1[$key]['status']) . '</td>' .
														'<td><a href="#" onclick="viewPackageInfo(' . $key . ');"><span class="glyphicon glyphicon-eye-open"></span></a></td>' .
														'</tr>'
														);
												}
											} else {
												print('<tr><td>There are no purchases to show!</td></tr>');
											}

										?>
									</tbody>
								</table>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
		<div id="footer">
			<?php printFooter(); ?>
		</div>

		<script src="js/bootstrap.js"></script>
		<script src="js/main.js"></script>
		<script>
		<?php
			if(isset($_SESSION['linksteam'])){
				print('
					var html = \'\' +
						\'<form id="link-form" action="ajax/usersettings.php" method="post">\n\' +
							\'<p style="text-align: center;">You are about to link the steam account with Steam ID ' . $_SESSION['steamid'] . ' to this account. This will also move any purchases associated with this Steam account to your current account. Are you sure you want to do this?</p>\n\' +
							\'<input type="hidden" value="" name="linksteamaccount">\n\' +
							\'<input class="submit-button" type="submit" value="Link" name="submit" style="margin-left: auto; margin-right: auto; margin-bottom: 0px;">\n\' +
						\'</form>\';
					showError(html);
			');
			}
		?>

			var results = <?php print($resultsJS); ?>;

			function changePassword(){
				var html = '' +
					'<form id="changepassword-form" action="ajax/usersettings.php" method="post" enctype="multipart/form-data">\n' +
						'<input type="password" name="changepasswordcurrent" class="settings-text-input" style="margin-bottom: 10px;" placeholder="Current Password">\n' +
						'<input type="password" name="changepassword" class="settings-text-input" style="margin-bottom: 10px;" placeholder="New Password">\n' +
						'<input type="password" name="changepasswordconfirm" class="settings-text-input" style="margin-bottom: 10px;" placeholder="Confirm New Password">\n' +
						'<input type="hidden" value="<?= $_SESSION['csrftoken'] ?>" name="csrftoken">\n' +
						'<input class="submit-button" type="submit" value="Change Password" name="submit">\n' +
					'</form>';

				showError(html);

				$('#changepassword-form').on('submit', function (e) {
					e.preventDefault();
					$.ajax({
						type: 'post',
						url: 'ajax/usersettings.php',
						data: new FormData( this ),
		  				processData: false,
		  				contentType: false,
						success: function (data) {
							if($.trim(data)){
								$('#errorbox-content-1').remove();
								$('#errorbox-bottom-1').append('<div id="errorbox-content">' + data + '</div>');
								if($('#table-container-1').css('display') == 'none'){
									showError1();
								}
							} else {
								$('#errorbox-content-1').remove();
								$('#errorbox-bottom-1').append('Password successfully changed.');
								if($('#table-container-1').css('display') == 'none'){
									showError1();
								}
							}
						}
					});
				});
			}

			function addEmail(){
				var html = '' +
					'<form id="email-form" action="ajax/usersettings.php" method="post" enctype="multipart/form-data">\n' +
						'<input id="changeemail" name="changeemail" type="text" name="changeemail" class="settings-text-input" style="margin-bottom: 10px;">\n' +
						'<input type="hidden" value="<?= $_SESSION['csrftoken'] ?>" name="csrftoken">\n' +
						'<input class="submit-button" type="submit" value="Change Email Address" name="submit">\n' +
					'</form>';

				showError(html);

				$('#email-form').on('submit', function (e) {
					e.preventDefault();
					$.ajax({
						type: 'post',
						url: 'ajax/usersettings.php',
						data: new FormData( this ),
		  				processData: false,
		  				contentType: false,
						success: function (data) {
							if($.trim(data)){
								$('#errorbox-content-1').remove();
								$('#errorbox-bottom-1').append('<div id="errorbox-content">' + data + '</div>');
								if($('#table-container-1').css('display') == 'none'){
									showError1();
								}
							} else {
								$('#errorbox-content-1').remove();
								$('#errorbox-bottom-1').append('Email address successfully changed.');
								if($('#table-container-1').css('display') == 'none'){
									showError1();
								}
							}
						}
					});
				});
			}

			function addPaypalCredit(){
				var html = '' +
					'<div class="setting-title">Amount (<?= $currencycode ?>)</div>\n' +
					'<input type="text" class="settings-text-input" id="credit-amount" name="credit-amount">\n' +
					'<form method="post" action="<?= $paypalURL; ?>" id="paypal-credit-form">\n' +
						'<input type="hidden" name="cmd" value="<?php echo $buttonType; ?>">\n' +
						'<input type="hidden" name="notify_url" value="<?=$dir?>paypalipn.php">\n' +
						'<input type="hidden" name="amount" value="0.00" id="amount">\n' +
						'<input type="hidden" name="business" id="paypal-business">\n' +
						'<input type="hidden" name="currency_code" value="<?= $currencycode ?>">\n' +
						'<input type="hidden" name="no_shipping" value="1">\n' +
						'<input type="hidden" name="custom" value="<?=$userID?>">\n' +
						'<input type="hidden" name="return" value="<?php echo $dir . "account.php?paypalcreditreturn="; ?>">\n' +
						'<input type="hidden" name="cancel_return" value="<?php echo $dir . "account.php"; ?>">\n' +
						'<input type="hidden" name="item_name" value="SDonate Credit">\n' +
					'</form>\n' +
					'<input class="buy-button-1" type="image" id="paypal-checkout-button" src="https://www.paypalobjects.com/webstatic/en_US/btn/btn_checkout_pp_142x27.png" onclick="creditPurchase();">';

				showError(html);

				$('#credit-amount').change(function(){
					var value = $(this).val();
					$("#amount").val(value);
				});
			}

			function creditPurchase(){
				addLoadingCircle($("#errorbox-bottom"));
				var formData = new FormData();
				formData.append('creditpurchase', '1');
				$.ajax({
					type: 'post',
					url:  'ajax.php',
					data: formData,
					processData: false,
					contentType: false,
					success: function (data) {
						if($.trim(data).substring(0, 3) !== "ID:"){
							$('#errorbox-content').remove();
							$('#errorbox-bottom').append('<div id="errorbox-content">' + data + '</div>');
							if($('#table-container').css('display') == 'none'){
								showError();
							}
							removeLoadingCircle($("#errorbox-bottom"));
						} else {
							$("#paypal-business").val($.trim(data).substring(3));
							$("#paypal-credit-form").submit();
						}
					}
				});
			}

			function viewPackageInfo(key){

				var expires = "";

				if(results[key]["expires"] === "0"){
					expires = '<p>Never</p>';
				} else {
					expires = '<p>' + results[key]["expiretime"] + '</p>';
				}

				var html = '' +
					'<p id="errorbox-title">Transaction Info</p>\n' +
					'<p class="setting-title">Date of Transaction<button type="button" class="btn btn-default btn-sm tooltip-btn" data-toggle="tooltip" data-placement="top" title="Date and time when the transaction took place in the format YY-MM-DD hh:mm:ss.">?</button></p>\n' +
					'<p>' + results[key]["time"] + '</p>\n' +
					'<p class="setting-title"><?= getLangString("value"); ?><button type="button" class="btn btn-default btn-sm tooltip-btn" data-toggle="tooltip" data-placement="top" title="Value of the transaction.">?</button></p>\n' +
					'<p>' + <?php print("'" . $currencysymbol . "'"); ?>  + results[key]["value"] + '</p>\n' +
					'<p class="setting-title">Payment Method<button type="button" class="btn btn-default btn-sm tooltip-btn" data-toggle="tooltip" data-placement="top" title="The payment method used for this transaction.">?</button></p>\n' +
					'<p>' + results[key]["paymentmethod"] + '</p>\n' +
					'<p class="setting-title"><?= getLangString("game"); ?><button type="button" class="btn btn-default btn-sm tooltip-btn" data-toggle="tooltip" data-placement="top" title="The game this package applied to.">?</button></p>\n' +
					'<p>' + results[key]["game"] + '</p>\n' +
					'<p class="setting-title"><?= getLangString("package"); ?><button type="button" class="btn btn-default btn-sm tooltip-btn" data-toggle="tooltip" data-placement="top" title="The name of the package purchased.">?</button></p>\n' +
					'<p>' + results[key]["package"] + '</p>\n' +
					'<p class="setting-title">' + results[key]["usernametype"] + '<button type="button" class="btn btn-default btn-sm tooltip-btn" data-toggle="tooltip" data-placement="top" title="The ' + results[key]["usernametype"] + ' which the package was applied to.">?</button></p>\n' +
					'<p>' + results[key]["username"] + '</p>\n' +
					'<p class="setting-title">Expires<button type="button" class="btn btn-default btn-sm tooltip-btn" data-toggle="tooltip" data-placement="top" title="Date and time this package expires on.">?</button></p>\n' +
					expires;

					showError(html);
					enableToolTips();
			}

			function submissionSuccess(){
				location.reload(true);
			}

			function listenForSubmit(){
				$('#link-form').on('submit', function (e) {
					e.preventDefault();
					$.ajax({
						type: 'post',
						url:  $(this).attr('action'),
						data: new FormData( this ),
		  				processData: false,
		  				contentType: false,
						success: function (data) {
							if($.trim(data)){
								$('#errorbox-content').remove();
								$('#errorbox-bottom').append('<div id="errorbox-content">' + data + '</div>');
								if($('#table-container').css('display') == 'none'){
									showError();
								}
							} else {
								submissionSuccess();
							}
						}
					});
				});
			}

			listenForSubmit();

			<?php

				if(isset($codeAdded)){
					print("
						$('#errorbox-content').remove();
						$('#errorbox-bottom').append('€3" . getLangString("credit-added") . "');
						if($('#table-container').css('display') == 'none'){
							showError();
						}
					");
					$codeAdded = false;
				}

			?>

		</script>

	</body>

</html>

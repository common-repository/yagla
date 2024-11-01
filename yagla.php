<?php
/**
 * @package Yagla
 * @version 1.2
 */
/*
Plugin Name: YAGLA
Plugin URI: https://yagla.ru/help/plagin-dlya-wordpress
Description: Плагин для работы с сервисом yagla.ru.
Author: ООО Ягла
Author URI:  https://yagla.ru/?utm_source=wpplugin
Version: 1.3
*/

class YAGLA {

	//https://wordpress.org/plugins/add/

	const YAGLA_VERSION = "1.0";
	const YAGLA_BASESCRIPT = "//st.yagla.ru/js/y.c.js";
	const YAGLA_SCRIPT_URL = "//st.yagla.ru/js/";
	const YAGLA_URL = 'https://yagla.ru/api/';
	private $YaglaHash = '';
	private $isEnableIntegrationCF7 = false;
	private $errors = array();
	public function Enable() {
		$this->YaglaHash = get_option( 'yaglahash' );
		if($this->YaglaHash) {
			$this->isEnableIntegrationCF7 = get_option( 'yaglaCF7' )=="1";
			add_action( 'wp_enqueue_scripts', array($this, "AddScript"));
		}
		if(is_admin()){
			add_action('admin_menu', array($this,'AdminAddPage'));
			add_action( 'admin_enqueue_scripts',  array($this, "yagla_admin_scripts"));
		}
	}
	public function AdminAddPage() {
		add_options_page( 'Yagla', 'Yagla', 'manage_options', 'yaglasettings', array($this,'admin_page_content'));
	}
	function yagla_admin_scripts() {
		wp_enqueue_script( 'jquery-ui-tabs', array('jquery-ui-core') ); // Load jQuery UI Tabs JS for Admin Page
		wp_enqueue_script( 'yagla-admin',  plugin_dir_url(__FILE__) . 'js/admin.js', array('jquery','jquery-ui-tabs') ); // Load specific JS for Admin Page

		wp_enqueue_style( 'jquery-ui', plugin_dir_url(__FILE__) . 'css/jquery-ui.css' ); // Load jQuery UI Tabs CSS for Admin Page
	}
	function yagla_admin_styles() {

	}

	public function requesttoYagla($action, $PARAMS) {
		$result =null;
		$PARAMS['hash'] = 'EbVYiFgTLsas7l3z9WBCZdAwaNwHKDr6'; //Ключ для подключения
		$args = array(
			'body' => $PARAMS,
			'timeout' => '30',
			'redirection' => '5',
			'httpversion' => '1.0',
			'headers' => array(),
			'cookies' => array()
		);
		$response= wp_remote_post( YAGLA::YAGLA_URL.$action, $args);
		$http_code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		if($http_code==200) {
			$result = json_decode($body);
		}
		return $result;
	}

	public function savescript($script) {
		if ( $script ) {
			$script = trim($script);
			$script = str_replace('\"','"',$script);
			$result = preg_match_all("/<script.+src=(?:\"|')(?:[^\/]*)(?:[\/\/]+st\.yagla\.ru\/js\/y.c\.js\?h=)([^\"\']*)(?:\"|').*>/", $script, $matches);
			$hash = $matches[1][0];
			if($hash) {
				$this->YaglaHash = $hash;
				update_option( 'yaglahash', $this->YaglaHash );
			}
			else {
				$this->errors[] = "неверный формат кода";
			}
		}
	}
	public function deletehash() {
		delete_option( 'yaglahash' );
		$this->YaglaHash = "";
	}
	public function gethash($email,$password) {
		if ( $email && $password) {
			$PARAMS['email'] =$email;
			$PARAMS['password'] =$password;
			$res = $this->requesttoYagla('getUserScript', $PARAMS);
			if($res->result=="success") {
				$this->YaglaHash =  $res->hash;
				update_option( 'yaglahash', $this->YaglaHash );
			}
			else
			{
				$this->errors[] = $res->error;
			}
		}
	}
	public function AddScript(){
		wp_enqueue_script('yaglascript', YAGLA::YAGLA_BASESCRIPT."?h=".$this->YaglaHash, array(),null);
		if($this->isEnableIntegrationCF7) {
			wp_add_inline_script('yaglascript', "document.addEventListener( 'wpcf7mailsent', function( event ) { if(typeof yaglaaction=='function')yaglaaction('wpcf7');}, false );");
		}
	}

	private  function saveSettings ()
	{
		$this->isEnableIntegrationCF7=false;
		if(isset($_POST["enableCF7goals"]))
		{
			$this->isEnableIntegrationCF7 =  sanitize_key($_POST["enableCF7goals"])=="1";
		}
		update_option( 'yaglaCF7', $this->isEnableIntegrationCF7 ? "1":"0");
	}
	public function admin_page_content() {
		if ( isset( $_POST['save-script'] ) && wp_verify_nonce( $_POST['save-script'], 'yaglasettings' ) ) {
			$this->savescript($_POST['yagla-script']);
		}

		if ( isset( $_POST['get-hash'] ) && wp_verify_nonce( $_POST['get-hash'], 'yaglasettings' ) ) {
			$this->gethash(sanitize_email($_POST["yagla-email"]),sanitize_text_field($_POST["yagla-password"]));
		}
		if ( isset( $_POST['deleteyaglacode'] ) && wp_verify_nonce( $_POST['deleteyaglacode'], 'yaglasettings' ) ) {
			$this->deletehash();
		}
		if ( isset( $_POST['save-settings'] ) && wp_verify_nonce( $_POST['extsettings'], 'yaglasettings' ) ) {
			$this->saveSettings();
		}
		?>
		<div class="wrap">
			<img src="<?php echo plugin_dir_url(__FILE__)?>Yagla-Logo.png" alt="Yagla"/>
			<?php if(count($this->errors)):?>
			<div style="color: red;padding: 20px 5px;border: red solid 1px;margin: 10px 0;"><?php echo implode("<br/>",$this->errors);?></div>
			<?php endif?>
			<div></div>
			<?php if($this->YaglaHash):?>
			<div class="postbox">
			<div style="color: green; font-size:18px;padding:10px;">Код установлен</div>
					<form id="config-form" action="#" method="post" style="padding:10px;">
						<?php wp_nonce_field( 'yaglasettings', 'deleteyaglacode' ); ?>
						<input type="submit" name="submit" class="button button-primary" value="Удалить код"/>
					</form>

			</div>
			<div class="postbox">
			<div style="font-size:18px;font-weight:bold; padding:10px;">Дополнительные настройки</div>
					<form id="config-form" action="#" method="post" style="padding:10px;">
						<?php wp_nonce_field( 'yaglasettings', 'extsettings' ); ?>
						<p><input type="checkbox" value="1" name="enableCF7goals" id="setting-enable-CF7-goals" <?php if($this->isEnableIntegrationCF7):?>checked<?php endif?>/><label for="setting-enable-CF7-goals">Интеграция с Contact Form 7.</label>
							<br/><span style="color:#666; font-size:0.9em">При включенной интеграции после успешной отправке любой формы плагина Contact Form 7 будет передано в yagla.ru событие <b>wpcf7</b> для настройки целей. </span>
						</p>
						<input type="submit" name="save-settings" class="button button-primary" value="Сохранить"/>
					</form>

			</div>
			<?php else:?>
			<div class="postbox" style="padding:10px">
			<div style="color: red; font-size:18px;margin-bottom:20px">Код не установлен</div>
				<div style="margin-bottom:20px;">Для установки кода выберите один из вариантов:</div>
				<div id="tabs">
				  <ul>
					<li><a href="#tabs-1">Укажите код скрипта</a></li>
					<li><a href="#tabs-2">Укажите email и пароль от сервиса Yagla.ru</a></li>
				  </ul>
				  <div id="tabs-1">
					<p>Вставьте код скрипта, который отображается на странице "код" вашего проекта в Yagla.ru</p>
									<form id="config-form1" action="#" method="post" style="padding:10px;">
										<?php wp_nonce_field( 'yaglasettings', 'save-script' ); ?>
										<br/>
										<textarea name="yagla-script" style="width:100%; height:100px;"></textarea><br/><br/>
										<input type="submit" name="submit" class="button button-primary" value="Установить код скрипта"/>
									</form>

				  </div>
				  <div id="tabs-2">
									<form id="config-form3" action="#" method="post" style="padding:10px;">
										<?php wp_nonce_field( 'yaglasettings', 'get-hash' ); ?>
										<h3></h3>
										 и мы сами получим данные для установки кода из вашего аккаунта. <br/>
										<b>Внимание! Данные не сохраняются и используются только для получения скрипта</b>
										<br/>
										<div style="padding-top:10px">
											<table>
												<tr>
													<td><label for="yaglaauthlogon">Ваш email в yagla.ru<label></td>
													<td><input name="yagla-email" value="" type="email" style="margin-right:10px;" id="yaglaauthlogon"/></td>
												</tr>
												<tr>
													<td><label for="yaglaauthpass">Ваш пароль в yagla.ru<label></td>
													<td><input name="yagla-password" value="" type="password" style="margin-right:10px;" id="yaglaauthpass"/></td>
												</tr>
											</table>

											<br/><br/>
											<input type="submit" name="submit" class="button button-primary" value="Получить код скрипта и Установить"/>
										</div>
									</form>
				  </div>

				</div>

				<div class="clear"></div>
			</div>
		</div>

		<?php endif?>
	<div style="border-top:1px dashed #DDDDDD;margin:20px 0 40px;overflow:hidden;padding-top:25px;width:100%;float:left">

		Плагин создан и поддерживается <a href="https://yagla.ru?utm_source=wpplugin" target=_blank>yagla.ru</a>, <br/>в случае возникновения вопросов или найденных ошибках пишите на <a href="mailto:wpplugin@yagla.ru">wpplugin@yagla.ru</a>

	</div>

		<?php
	}


}
$yagla = new  YAGLA();
$yagla->Enable();

?>

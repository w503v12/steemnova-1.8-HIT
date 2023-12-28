<?php

/**
 *  2Moons
 *   by Jan-Otto Kröpke 2009-2016
 *
 * For the full copyright and license information, please view the LICENSE
 *
 * @package 2Moons
 * @author Jan-Otto Kröpke <slaver7@gmail.com>
 * @copyright 2009 Lucky
 * @copyright 2016 Jan-Otto Kröpke <slaver7@gmail.com>
 * @licence MIT
 * @version 1.8.x Koray Karakuş <koraykarakus@yahoo.com>
 * @link https://github.com/jkroepke/2Moons
 */

abstract class AbstractGamePage
{
	/**
	 * reference of the template object
	 * @var template
	 */
	protected $tplObj;

	/**
	 * reference of the template object
	 * @var ResourceUpdate
	 */
	protected $ecoObj;
	protected $window;
	protected $disableEcoSystem = false;

	protected function __construct() {

		if(!AJAX_REQUEST)
		{
			$this->setWindow('full');
			if(!$this->disableEcoSystem)
			{
				$this->ecoObj	= new ResourceUpdate();
				$this->ecoObj->CalcResource();
			}
			$this->initTemplate();
		} else {
			$this->setWindow('ajax');
		}
	}

	protected function GetFleets() {
		global $USER, $PLANET;
		require_once 'includes/classes/class.FlyingFleetsTable.php';
		$fleetTableObj = new FlyingFleetsTable;
		$fleetTableObj->setUser($USER['id']);
		$fleetTableObj->setPlanet($PLANET['id']);
		return $fleetTableObj->renderTable();
	}

	protected function initTemplate() {
		global $config, $USER;

		if(isset($this->tplObj))
			return true;

		$this->tplObj	= new template;
		list($tplDir)	= $this->tplObj->getTemplateDir();

		$path = $theme = "";

		$theme = ($config->let_users_change_theme) ? $USER['dpath'] : $config->server_default_theme;

		$path = "theme/" . $theme;
		

		$this->tplObj->setTemplateDir($tplDir. $path);
		return true;
	}

	protected function setWindow($window) {
		$this->window	= $window;
	}

	protected function getWindow() {
		return $this->window;
	}

	protected function getQueryString() {
		$queryString	= array();
		$page			= HTTP::_GP('page', '');

		if(!empty($page)) {
			$queryString['page']	= $page;
		}

		$mode			= HTTP::_GP('mode', '');
		if(!empty($mode)) {
			$queryString['mode']	= $mode;
		}

		return http_build_query($queryString);
	}

	protected function getCronjobsTodo()
	{
		require_once 'includes/classes/Cronjob.class.php';

		$this->assign(array(
			'cronjobs'		=> Cronjob::getNeedTodoExecutedJobs()
		));
	}

	protected function getNavigationData()
	{
		global $PLANET, $LNG, $USER, $THEME, $resource, $reslist, $config;


		$PlanetSelect	= array();

		if($USER['bana'] == 1) {
			echo 'You received a Ban. If you think this is a mistake, write on our Discord: <a href="https://discord.gg/g6UHwXE">https://discord.gg/g6UHwXE</a>'; die();
		}

		if(isset($USER['PLANETS'])) {
			$USER['PLANETS']	= getPlanets($USER);
		}

		foreach($USER['PLANETS'] as $PlanetQuery)
		{
			$PlanetSelect[$PlanetQuery['id']]	= $PlanetQuery['name'].(($PlanetQuery['planet_type'] == 3) ? " (" . $LNG['fcm_moon'] . ")":"")." [".$PlanetQuery['galaxy'].":".$PlanetQuery['system'].":".$PlanetQuery['planet']."]";
		}

		$resourceTable	= array();
		$resourceSpeed	= $config->resource_multiplier;
		foreach($reslist['resstype'][1] as $resourceID)
		{
			$resourceTable[$resourceID]['name']	= $resource[$resourceID];
			$resourceTable[$resourceID]['current'] = $PLANET[$resource[$resourceID]];
			$resourceTable[$resourceID]['max']	= $PLANET[$resource[$resourceID].'_max'];

			if($USER['urlaubs_modus'] == 1 || $PLANET['planet_type'] != 1)
			{
				$resourceTable[$resourceID]['production']	= $PLANET[$resource[$resourceID].'_perhour'];
			}
			else
			{
				$resourceTable[$resourceID]['production']	= $PLANET[$resource[$resourceID].'_perhour'] + $config->{$resource[$resourceID].'_basic_income'} * $resourceSpeed;
			}
		}

		foreach($reslist['resstype'][2] as $resourceID)
		{
			$resourceTable[$resourceID]['name']			= $resource[$resourceID];
			$resourceTable[$resourceID]['used']			= $PLANET[$resource[$resourceID].'_used'];
			$resourceTable[$resourceID]['max']			= $PLANET[$resource[$resourceID]];
		}

		foreach($reslist['resstype'][3] as $resourceID)
		{
			$resourceTable[$resourceID]['name']			= $resource[$resourceID];
			$resourceTable[$resourceID]['current']		= $USER[$resource[$resourceID]];
		}

		$themeSettings	= $THEME->getStyleSettings();

		$commit = '';
		$commitShort = '';
		if(file_exists('.git/FETCH_HEAD'))
		{
			$commit = explode('	', file_get_contents('.git/FETCH_HEAD'))[0];
			$commitShort = substr($commit, 0, 7);
		}

		$avatar = 'styles/resource/images/user.png';
		if (Session::load()->data !== null)
		{
			try{
				$avatar = json_decode(Session::load()->data->account->json_metadata)->profile->profile_image;
			}catch(Exception $e){}
		}


		$this->assign(array(
			'PlanetSelect'		=> $PlanetSelect,
			'new_message' 		=> $USER['messages'],
			'commit'			=> $commit,
			'commitShort'		=> $commitShort,
			'vacation'			=> $USER['urlaubs_modus'] ? _date($LNG['php_tdformat'], $USER['urlaubs_until'], $USER['timezone']) : false,
			'delete'			=> $USER['db_deaktjava'] ? sprintf($LNG['tn_delete_mode'], _date($LNG['php_tdformat'], $USER['db_deaktjava'] + ($config->del_user_manually * 86400)), $USER['timezone']) : false,
			'darkmatter'		=> $USER['darkmatter'],
			'current_pid'		=> $PLANET['id'],
			'image'				=> $PLANET['image'],
			'username'			=> $USER['username'],
			'avatar'			=> $avatar,
			'resourceTable'		=> $resourceTable,
			'shortlyNumber'		=> $themeSettings['TOPNAV_SHORTLY_NUMBER'],
			'closed'			=> !$config->game_disable,
			'hasBoard'			=> filter_var($config->forum_url, FILTER_VALIDATE_URL),
			'hasAdminAccess'	=> !empty(Session::load()->adminAccess),
			'hasGate'			=> $PLANET[$resource[43]] > 0,
			'discordUrl'		=> DISCORD_URL,
			//overwrite messages, to do : delete from other pages
			'messages'					=> ($USER['messages'] > 0) ? (($USER['messages'] == 1) ? $LNG['ov_have_new_message'] : "(" . $USER['messages'] . ")"): false,
		));
	}

	protected function getPageData()
	{
		global $USER, $THEME, $config, $PLANET, $LNG;

		if($this->getWindow() === 'full') {
			$this->getNavigationData();
			$this->getCronjobsTodo();
		}

		$dateTimeServer		= new DateTime("now");
		if(isset($USER['timezone'])) {
			try {
				$dateTimeUser	= new DateTime("now", new DateTimeZone($USER['timezone']));
			} catch (Exception $e) {
				$dateTimeUser	= $dateTimeServer;
			}
		} else {
			$dateTimeUser	= $dateTimeServer;
		}

		$AllPlanets = $AllMoons = array();
		foreach($USER['PLANETS'] as $ID => $CPLANET)
		{

			if (!empty($CPLANET['b_building']) && $CPLANET['b_building'] > TIMESTAMP) {
				$Queue = unserialize($CPLANET['b_building_id']);
				$BuildPlanet = $LNG['tech'][$Queue[0][0]]." (".$Queue[0][1].")<br><span style=\"color:#7F7F7F;\">(".pretty_time($Queue[0][3] - TIMESTAMP).")</span>";
			} else {
				$BuildPlanet = $LNG['ov_free'];
			}

			if ($CPLANET['planet_type'] == 3) {

				$AllMoons[] = array(
					'id'	=> $CPLANET['id'],
					'name'	=> (strlen($CPLANET['name']) >= 12) ? substr($CPLANET['name'],0,12) . ".." : $CPLANET['name'],
					'image'	=> $CPLANET['image'],
					'build'	=> $BuildPlanet,
					'galaxy' => $CPLANET['galaxy'],
					'system' => $CPLANET['system'],
					'planet' => $CPLANET['planet'],
					'selected' => ($CPLANET['id'] == $PLANET['id']) ? true : false,
					'field_current' => $CPLANET['field_current'],
					'field_max' => $CPLANET['field_max'],
					'diameter' => $CPLANET['diameter'],
					'temp_min' => $CPLANET['temp_min'],
					'temp_max' => $CPLANET['temp_max'],
				);

			}else {

				$AllPlanets[] = array(
					'id'	=> $CPLANET['id'],
					'name'	=> (strlen($CPLANET['name']) >= 12) ? substr($CPLANET['name'],0,12) . ".." : $CPLANET['name'],
					'image'	=> $CPLANET['image'],
					'build'	=> $BuildPlanet,
					'galaxy' => $CPLANET['galaxy'],
					'system' => $CPLANET['system'],
					'planet' => $CPLANET['planet'],
					'selected' => ($CPLANET['id'] == $PLANET['id']) ? true : false,
					'field_current' => $CPLANET['field_current'],
					'field_max' => $CPLANET['field_max'],
					'diameter' => $CPLANET['diameter'],
					'temp_min' => $CPLANET['temp_min'],
					'temp_max' => $CPLANET['temp_max'],
					'id_luna' => $CPLANET['id_luna'],
				);

			}



		}

		// NOTE: add moon array inside planet array
		foreach ($AllPlanets as $key => &$currentPlanet) {
			if ($currentPlanet['id_luna'] == 0) {
				continue;
			}

			foreach ($AllMoons as $moon_key => $currentMoon) {
				if ($currentMoon['id'] == $currentPlanet['id_luna']) {
					$currentPlanet['moonInfo'][] = $currentMoon;
				}else {
					$currentPlanet['moonInfo'][] = array();
				}
			}

		}




		$this->assign(array(
			'vmode'				=> $USER['urlaubs_modus'],
			'authlevel'			=> $USER['authlevel'],
			'userID'			=> $USER['id'],
			'bodyclass'			=> $this->getWindow(),
			'game_name'			=> $config->game_name,
			'uni_name'			=> $config->uni_name,
			'game_speed' => pretty_number($config->game_speed / 2500),
			'fleet_speed' => pretty_number($config->fleet_speed / 2500),
			'production_speed' => pretty_number($config->resource_multiplier),
			'storage_multiplier' => pretty_number($config->storage_multiplier),
			'ga_active'			=> $config->ga_active,
			'ga_key'			=> $config->ga_key,
			'debug'				=> $config->debug,
			'VERSION'			=> $config->VERSION,
			'date'				=> explode("|", date('Y\|n\|j\|G\|i\|s\|Z', TIMESTAMP)),
			'isPlayerCardActive' => isModuleAvailable(MODULE_PLAYERCARD),
			'REV'				=> substr($config->VERSION, -4),
			'Offset'			=> $dateTimeUser->getOffset() - $dateTimeServer->getOffset(),
			'queryString'		=> $this->getQueryString(),
			'themeSettings'		=> $THEME->getStyleSettings(),
			'page' => HTTP::_GP('page',''),
			'mode' => HTTP::_GP('mode',''),
			'servertime' => _date("M D d H:i:s", TIMESTAMP, $USER['timezone']),
			'AllPlanets'				=> $AllPlanets,
			'fleets'					=> $this->GetFleets(),
			'show_fleets_active' => $USER['show_fleets_active']
		));
	}
	protected function printMessage($message, $redirectButtons = NULL, $redirect = NULL, $fullSide = true)
	{
		$this->assign(array(
			'message'			=> $message,
			'redirectButtons'	=> $redirectButtons,
		));

		if(isset($redirect)) {
			$this->tplObj->gotoside($redirect[0], $redirect[1]);
		}

		if(!$fullSide) {
			$this->setWindow('popup');
		}

		$this->display('error.default.tpl');
	}

	protected function save() {
		if(isset($this->ecoObj)) {
			$this->ecoObj->SavePlanetToDB();
		}
	}

	protected function assign($array, $nocache = true) {
		$this->tplObj->assign_vars($array, $nocache);
	}

	protected function display($file) {
		global $THEME, $LNG;

		$this->save();

		if($this->getWindow() !== 'ajax') {
			$this->getPageData();
		}

		$this->assign(array(
			'lang'    		=> $LNG->getLanguage(),
			'dpath'			=> $THEME->getTheme(),
			'scripts'		=> $this->tplObj->jsscript,
			'execscript'	=> implode("\n", $this->tplObj->script),
			'basepath'		=> PROTOCOL.HTTP_HOST.HTTP_BASE,
		));

		$this->assign(array(
			'LNG'			=> $LNG,
		), false);

		$this->tplObj->display('extends:layout.'.$this->getWindow().'.tpl|'.$file);
		exit;
	}

	protected function sendJSON($data) {
		$this->save();
		echo json_encode($data);
		exit;
	}

	protected function redirectTo($url) {
		$this->save();
		HTTP::redirectTo($url);
		exit;
	}
}

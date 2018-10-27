<?php
header("Content-type: text/html; charset=UTF-8");

/**
 * @package     Joomla - > Site and Administrator payment info
 * @subpackage  com_Rsfrom
 * @subpackage 	ebrahimi_payping
 * @copyright   erfan ebrahimi => http://erfanebrahimi.ir
 * @copyright   Copyright (C) 20018 Open Source Matters, Inc. All rights reserved.
 */

// no direct access
defined('_JEXEC') or die('Restricted access');
if (!class_exists ('checkHack')) {
	require_once( JPATH_PLUGINS . '/system/rsfpayping/payping_inputcheck.php');
}

class plgSystemRSFPPayping extends JPlugin {
	var $componentId = 200;
	var $componentValue = 'payping';
	
	public function __construct( &$subject, $config )
	{
		parent::__construct( $subject, $config );
		$this->newComponents = array(200);
	}
	
	function rsfp_bk_onAfterShowComponents() {
		$lang = JFactory::getLanguage();
		$lang->load('plg_system_rsfpayping');
		$db = JFactory::getDBO();
		$formId = JRequest::getInt('formId');
		$link = "displayTemplate('" . $this->componentId . "')";
		if ($components = RSFormProHelper::componentExists($formId, $this->componentId))
		   $link = "displayTemplate('" . $this->componentId . "', '" . $components[0] . "')";
?>
        <li class="rsform_navtitle"><?php echo 'درگاه پی‌پینگ'; ?></li>
		<li><a href="javascript: void(0);" onclick="<?php echo $link; ?>;return false;" id="rsfpc<?php echo $this->componentId; ?>"><span id="PAYPING"><?php echo JText::_('اضافه کردن درگاه پی‌پینگ'); ?></span></a></li>
		
		
		<?php
		
	}
	
	function rsfp_getPayment(&$items, $formId) {
		if ($components = RSFormProHelper::componentExists($formId, $this->componentId)) {
			$data = RSFormProHelper::getComponentProperties($components[0]);
			$item = new stdClass();
			$item->value = $this->componentValue;
			$item->text = $data['LABEL'];
			// add to array
			$items[] = $item;
		}
	}
	
	function rsfp_doPayment($payValue, $formId, $SubmissionId, $price, $products, $code) {//test
	    $app	= JFactory::getApplication();
		// execute only for our plugin
		if ($payValue != $this->componentValue) return;
		if ($price > 100) {
			if (RSFormProHelper::getConfig('payping.toman') == 0)
				$Amount = $price/10; // Toman
            else
                $Amount = $price ;
			$Description = 'پرداخت از طریق RSFORM';
			$CallbackURL = JURI::root() . 'index.php?option=com_rsform&formId=' . $formId . '&task=plugin&plugin_task=payping.notify&code=' . $code;


			$data = array( 'Amount' => $Amount, 'returnUrl' => $CallbackURL, 'Description' => $Description , 'clientRefId' => $formId  );
			try {
				$curl = curl_init();
				curl_setopt_array($curl, array(CURLOPT_URL => "https://api.payping.ir/v1/pay", CURLOPT_RETURNTRANSFER => true, CURLOPT_ENCODING => "", CURLOPT_MAXREDIRS => 10, CURLOPT_TIMEOUT => 30, CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1, CURLOPT_CUSTOMREQUEST => "POST", CURLOPT_POSTFIELDS => json_encode($data), CURLOPT_HTTPHEADER => array("accept: application/json", "authorization: Bearer " . RSFormProHelper::getConfig('payping.api'), "cache-control: no-cache", "content-type: application/json"),));
				$response = curl_exec($curl);
				$header = curl_getinfo($curl);
				$err = curl_error($curl);
				curl_close($curl);
				if ($err) {
					echo "cURL Error #:" . $err;
				} else {
					if ($header['http_code'] == 200) {
						$response = json_decode($response, true);
						if (isset($response["code"]) and $response["code"] != '') {
							$app->redirect(sprintf('https://api.payping.ir/v1/pay/gotoipg/%s', $response["code"]));
							exit;
						} else {
							$msg= $this->getGateMsg('notGetCode');
							$link = JURI::root() . 'index.php?option=com_rsform&formId=' . $formId;
							$app->redirect($link, '<h2>'.$msg. '</h2>', $msgType='Error');
						}
					} elseif ($header['http_code'] == 400) {
						$msg= $this->getGateMsg('400Error');
						$link = JURI::root() . 'index.php?option=com_rsform&formId=' . $formId;
						$app->redirect($link, '<h2>'.$msg.implode('. ',array_values (json_decode($response,true))). '</h2>', $msgType='Error');
					} else {
						$msg= $this->getGateMsg('400Error');
						$link = JURI::root() . 'index.php?option=com_rsform&formId=' . $formId;
						$app->redirect($link, '<h2>'.$msg.$this->getGateMsg($header['http_code']). '(' . $header['http_code'] . ')'. '</h2>', $msgType='Error');
					}
				}
			} catch (Exception $e){
				$msg= $this->getGateMsg('tryError');
				$link = JURI::root() . 'index.php?option=com_rsform&formId=' . $formId;
				$app->redirect($link, '<h2>'.$msg.$e->getMessage(). '</h2>', $msgType='Error');
			}
		}
		else {
			$msg= $this->getGateMsg('price'); 
			$link = JURI::root() . 'index.php?option=com_rsform&formId=' . $formId;
			$app->redirect($link, '<h2>'.$msg.'</h2>', $msgType='Error'); 
		}
	}
	
	function rsfp_bk_onAfterCreateComponentPreview($args = array()) {
		if ($args['ComponentTypeName'] == 'payping') {
			$args['out'] = '<td>&nbsp;</td>';
			$args['out'].= '<td>'.$args['data']['LABEL'].'</td>';
		}
	}
	
	function rsfp_bk_onAfterShowConfigurationTabs($tabs) {
		$lang = JFactory::getLanguage(); 
		$lang->load('plg_system_rsfppayping'); 
		$tabs->addTitle('تنظیمات درگاه پی‌پینگ', 'form-PAYPING');
		$tabs->addContent($this->paypingConfigurationScreen());
	}
  
	function rsfp_f_onSwitchTasks() {
		if (JRequest::getVar('plugin_task') == 'payping.notify') {
			$app	= JFactory::getApplication();
			$jinput = $app->input;
			$code 	= $jinput->get->get('code', '', 'STRING');
			$formId = $jinput->get->get('formId', '0', 'INT');
			$db 	= JFactory::getDBO();
			$db->setQuery("SELECT SubmissionId FROM #__rsform_submissions s WHERE s.FormId='".$formId."' AND MD5(CONCAT(s.SubmissionId,s.DateSubmitted)) = '".$db->escape($code)."'");
			$SubmissionId = $db->loadResult();
			//$mobile = $this::getPayerMobile ($formId,$SubmissionId);
			//===================================================================================
			$Authority = $jinput->get->get('refid', '', 'STRING');

			if ( checkHack::checkString($code) ){

				if (RSFormProHelper::getConfig('payping.toman') == 0)
					$Amount = round($this::getPayerPrice ($formId,$SubmissionId),0)/10; // Toman
				else
					$Amount = round($this::getPayerPrice ($formId,$SubmissionId),0) ;
				$data = array('refId' => $Authority, 'amount' => $Amount);
				try {
					$curl = curl_init();
					curl_setopt_array($curl, array(
						CURLOPT_URL => "https://api.payping.ir/v1/pay/verify",
						CURLOPT_RETURNTRANSFER => true,
						CURLOPT_ENCODING => "",
						CURLOPT_MAXREDIRS => 10,
						CURLOPT_TIMEOUT => 30,
						CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
						CURLOPT_CUSTOMREQUEST => "POST",
						CURLOPT_POSTFIELDS => json_encode($data),
						CURLOPT_HTTPHEADER => array(
							"accept: application/json",
							"authorization: Bearer ".RSFormProHelper::getConfig('payping.api'),
							"cache-control: no-cache",
							"content-type: application/json",
						),
					));
					$response = curl_exec($curl);
					$err = curl_error($curl);
					$header = curl_getinfo($curl);
					curl_close($curl);
					if ($err) {
						$msg= $this->getGateMsg('curlError').$err .$this->getGateMsg('trans').$Authority ;
						$link = JURI::root() . 'index.php?option=com_rsform&formId=' . $formId;
						$app->redirect($link, '<h2>'.$msg.'</h2>', $msgType='Error');
					} else {
						if ($header['http_code'] == 200) {
							$response = json_decode($response, true);
							if (isset($Authority) and $Authority != '') {
								if ($SubmissionId) {
									$db->setQuery("UPDATE #__rsform_submission_values sv SET sv.FieldValue=1 WHERE sv.FieldName='_STATUS' AND sv.FormId='".$formId."' AND sv.SubmissionId = '".$SubmissionId."'");
									$db->execute();
									$db->setQuery("UPDATE #__rsform_submission_values sv SET sv.FieldValue='"  . "کد پیگیری  "  . $Authority. "' WHERE sv.FieldName='transaction' AND sv.FormId='" . $formId . "' AND sv.SubmissionId = '" . $SubmissionId . "'");
									$db->execute();
									$mainframe = JFactory::getApplication();
									$mainframe->triggerEvent('rsfp_afterConfirmPayment', array($SubmissionId));
								}
								$app->enqueueMessage('<br />' . ' کد پیگیری شما' . $Authority, 'message');
							} else {
								$msg= $this->getGateMsg('cantGetTransCode').$this->getGateMsg($header['http_code']). '(' . $header['http_code'] . ')' ;
								$link = JURI::root() . 'index.php?option=com_rsform&formId=' . $formId;
								$app->redirect($link, '<h2>'.$msg.'</h2>', $msgType='Error');
							}
						} elseif ($header['http_code'] == 400) {
							$msg= $this->getGateMsg('400Error'). implode('. ',array_values (json_decode($response,true)))  .$this->getGateMsg('trans').$Authority ;
							$link = JURI::root() . 'index.php?option=com_rsform&formId=' . $formId;
							$app->redirect($link, '<h2>'.$msg.'</h2>', $msgType='Error');
						}  else {
							$msg= $this->getGateMsg('400Error'). $this->getGateMsg($header['http_code']). '(' . $header['http_code'] . ')' .$this->getGateMsg('trans').$Authority ;
							$link = JURI::root() . 'index.php?option=com_rsform&formId=' . $formId;
							$app->redirect($link, '<h2>'.$msg.'</h2>', $msgType='Error');
						}
					}
				} catch (Exception $e){
					$msg= $this->getGateMsg('tryError'). $e->getMessage() .$this->getGateMsg('trans').$Authority ;
					$link = JURI::root() . 'index.php?option=com_rsform&formId=' . $formId;
					$app->redirect($link, '<h2>'.$msg.'</h2>', $msgType='Error');
				}
			}
			else {
				$msg= $this->getGateMsg('hck2'); 
				$link = JURI::root() . 'index.php?option=com_rsform&formId=' . $formId;
				$app->redirect($link, '<h2>'.$msg.'</h2>', $msgType='Error'); 	
			}
		}
	}
	
	function paypingConfigurationScreen() {
		ob_start();
?>
		<div id="page-payping" class="com-rsform-css-fix">
			<table  class="admintable">
				<tr>
					<td width="200" style="width: 200px;" align="right" class="key"><label for="api"><?php echo 'مرچند کد'; ?></label></td>
					<td><input type="text" name="rsformConfig[payping.api]" value="<?php echo RSFormProHelper::htmlEscape(RSFormProHelper::getConfig('payping.api')); ?>" size="100" maxlength="64"></td>
				</tr>
				<tr>
					<td width="200" style="width: 200px;" align="right" class="key"><label for="toman"><?php echo 'واحد پولی :'; ?></label></td>
					<td><?php echo JHTML::_('select.booleanlist', 'rsformConfig[payping.toman]' , '' , RSFormProHelper::htmlEscape(RSFormProHelper::getConfig('payping.toman')));?></td>
				</tr>
			</table>
		</div>
	
		<?php
		$contents = ob_get_contents();
		ob_end_clean();
		return $contents;
	}
	
	function getGateMsg ($msgId) {
		switch($msgId){
			case 200 :
				return 'عملیات با موفقیت انجام شد';
				break ;
			case 400 :
				return 'مشکلی در ارسال درخواست وجود دارد';
				break ;
			case 500 :
				return 'مشکلی در سرور رخ داده است';
				break;
			case 503 :
				return 'سرور در حال حاضر قادر به پاسخگویی نمی‌باشد';
				break;
			case 401 :
				return 'عدم دسترسی';
				break;
			case 403 :
				return 'دسترسی غیر مجاز';
				break;
			case 404 :
				return 'آیتم درخواستی مورد نظر موجود نمی‌باشد';
				break;
			case	'1':
			case	'error': $out ='خطا غیر منتظره رخ داده است';break;
			case	'400Error': $out =' تراکنش ناموفق بود- شرح خطا : ';break;
			case	'tryError': $out =' تراکنش ناموفق بود- شرح خطا سمت برنامه شما : ';break;
			case	'curlError': $out ='خطا در ارتباط به پی‌پینگ : شرح خطا ';break;
			case	'cantGetTransCode': $out ='متافسانه سامانه قادر به دریافت کد پیگیری نمی باشد! نتیجه درخواست : ';break;
			case	'trans': $out =' شماره پیگیری : ';break;
			case	'hck2': $out = 'لطفا از کاراکترهای مجاز استفاده کنید';break;
			case	'notff': $out = 'سفارش پیدا نشد';break;
			case	'price': $out = 'مبلغ وارد شده کمتر از ۱۰۰۰ ریال می باشد';break;
			case	'notGetCode': $out = ' تراکنش ناموفق بود- شرح خطا : عدم وجود کد ارجاع ';break;
			default: $out ='خطا غیر منتظره رخ داده است';break;
		}
		return $out;
	}

	function getPayerMobile ($formId,$SubmissionId) {
		$db = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query->select('FieldValue')
			->from($db->qn('#__rsform_submission_values'));
		$query->where(
			$db->qn('FormId') . ' = ' . $db->q($formId) 
							. ' AND ' . 
			$db->qn('SubmissionId') . ' = ' . $db->q($SubmissionId)
							. ' AND ' . 
			$db->qn('FieldName') . ' = ' . $db->q('mobile')
		);
		$db->setQuery((string)$query); 
		$result = $db->loadResult();
		return $result;
	}

	function getPayerPrice ($formId,$SubmissionId) {
		$db = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query->select('FieldValue')
			->from($db->qn('#__rsform_submission_values'));
		$query->where(
			$db->qn('FormId') . ' = ' . $db->q($formId) 
							. ' AND ' . 
			$db->qn('SubmissionId') . ' = ' . $db->q($SubmissionId)
							. ' AND ' . 
			$db->qn('FieldName') . ' = ' . $db->q('price')
		);
		$db->setQuery((string)$query); 
		$result = $db->loadResult();
		return $result;
	}
}

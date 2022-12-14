<?php
chdir('..');
require_once('include/utils/utils.php');
require_once("vtlib/Vtiger/Module.php");
require_once("vtlib/Vtiger/Block.php");
require_once("vtlib/Vtiger/Field.php");
require_once('includes/runtime/Globals.php');
require_once('includes/runtime/LanguageHandler.php');
require_once('includes/runtime/BaseModel.php');
require_once('includes/Loader.php');
require_once("modules/ModTracker/models/Record.php");
require_once('modules/Vtiger/models/Module.php');
require_once('modules/Vtiger/models/Record.php');
require_once('modules/Users/models/Record.php');
require_once('modules/Accounts/Accounts.php');
require_once('modules/SalesOrder/SalesOrder.php');

ini_set('default_socket_timeout', -1);
ini_set('max_execution_time', 0);
ini_set('max_input_time', -1);
ini_set('memory_limit', '2048M');
ini_set('post_max_size', '1000M');
ini_set('upload_max_filesize', '1000M');


$adb = PearDatabase::getInstance();
$user = Users::getActiveAdminUser();
$current_user = Users_Record_Model::getCurrentUserModel();
if ((int) $current_user->id == 0) {
    $current_user = $user;
}

$querySelect = "SELECT DISTINCT salesorderid, subject, adjustment, subtotal, vtiger_salesorder.discount_amount, s_h_amount, pre_tax_total, tax1, tax2, tax3, charges FROM vtiger_salesorder
LEFT JOIN vtiger_inventoryproductrel ON vtiger_salesorder.salesorderid = vtiger_inventoryproductrel.id
LEFT JOIN vtiger_crmentity ON vtiger_salesorder.salesorderid = vtiger_crmentity.crmid
LEFT JOIN vtiger_inventorychargesrel ON vtiger_salesorder.salesorderid = vtiger_inventorychargesrel.recordid
WHERE vtiger_crmentity.deleted=0 AND vtiger_inventoryproductrel.tax1 = ?
GROUP BY vtiger_salesorder.salesorderid";

$queryUpdate = "UPDATE  vtiger_salesorder
LEFT JOIN vtiger_inventoryproductrel ON vtiger_salesorder.salesorderid = vtiger_inventoryproductrel.id
LEFT JOIN vtiger_crmentity ON vtiger_salesorder.salesorderid = vtiger_crmentity.crmid
SET     vtiger_inventoryproductrel.tax1 = ?, vtiger_salesorder.total = ?
WHERE   vtiger_crmentity.deleted=0  AND vtiger_salesorder.salesorderid = ?";

$tax1 = 8.0;

$results = $adb->pquery($querySelect, array($tax1));
if ($adb->num_rows($results) > 0) {
    $_REQUEST['ajxaction'] = 'DETAILVIEW';
    while ($row = $adb->fetchByAssoc($results)) {
        $saleOrderID = $row['salesorderid'];
        $saleOrderTax = $row['tax1'];
        $saleOrderSubject = $row['subject'];
        $chargesList = Zend_Json::decode(html_entity_decode($row['charges']));
        $newSaleOrderTax = 7.7;
        $totalAmount = 0.00;

        foreach ($chargesList as $chargeId => $chargeInfo) {
            foreach ($chargeInfo['taxes'] as $taxId => $taxPercent) {
                $calculatedOn = $chargeInfo['value'];
                $totalAmount += ((float) $calculatedOn * (float) $taxPercent) / 100;
                $shippingHandlingTax = $totalAmount;
            }
        }

        $netTotal = $row['subtotal'];
        $discountTotal = $row['discount_amount'];
        $shippingHandlingCharge = $row['s_h_amount'];
        $preTaxTotal = $row['pre_tax_total'];
        $taxVAT = $newSaleOrderTax;
        $taxSale = $row['tax2'];
        $taxService = $row['tax3'];
        $amount = (float) $netTotal - (float) $discountTotal;

        $taxValue = ((float) $taxVAT / 100 + (float) $taxSale / 100 + (float) $taxService / 100) * (float) $amount;
        $deductedTaxesAmount = 0.0;

        $grandTotal = (float) $netTotal - (float) $discountTotal + (float) $shippingHandlingCharge + (float) $shippingHandlingTax - (float) $deductedTaxesAmount + (float) $taxValue;

        $adb->pquery($queryUpdate, array($newSaleOrderTax, $grandTotal, $saleOrderID));

    }
};
<?php
if(PHP_SAPI!=='cli'){exit;}require '/var/www/html/wp-load.php';
use Pupilovo\SupplierHub\Domain\Pricing\PricingCalculator;
$n=0;function p_check($c,$l){if(!$c)throw new RuntimeException('FAIL: '.$l);++$GLOBALS['n'];echo 'PASS: '.$l.PHP_EOL;}$c=new PricingCalculator();
$base=['purchasePrice'=>100,'currency'=>'PLN','taxRate'=>23,'priceIncludesTax'=>false];
$markup=$c->calculate($base,['mode'=>'markup','value'=>20]);p_check($markup['status']==='calculated'&&$markup['salePriceGross']===147.6,'20 percent markup differs from margin');
$margin=$c->calculate($base,['mode'=>'margin','value'=>20]);p_check($margin['salePriceGross']===153.75,'20 percent margin uses cost divided by one minus margin');
$costs=$c->calculate($base,['mode'=>'markup','value'=>10,'shippingCost'=>10,'fixedCost'=>5,'returnBufferPercent'=>5,'paymentFeePercent'=>2,'rounding'=>['mode'=>'up','increment'=>1,'ending'=>0.99]]);p_check($costs['salePriceGross']>150&&$costs['additionalCostNet']>15,'additional costs and payment fee included');
$gross=$c->calculate(['purchasePrice'=>123,'currency'=>'PLN','taxRate'=>23,'priceIncludesTax'=>true],['mode'=>'markup','value'=>0]);p_check($gross['purchaseCostNet']===100.0&&$gross['salePriceGross']===123.0,'gross purchase converted to net and back');
$foreign=$c->calculate(['purchasePrice'=>10,'currency'=>'EUR','taxRate'=>23],['mode'=>'markup','value'=>20]);p_check($foreign['status']==='decision_required'&&in_array('missing_exchange_rate',$foreign['errors'],true),'foreign currency requires explicit rate');
$converted=$c->calculate(['purchasePrice'=>10,'currency'=>'EUR','taxRate'=>23],['mode'=>'markup','value'=>20,'exchangeRate'=>4.3]);p_check($converted['status']==='calculated'&&$converted['sourceCurrency']==='EUR','explicit exchange rate accepted');
$missing=$c->calculate(['currency'=>'PLN'],['mode'=>'markup','value'=>20]);p_check($missing['status']==='decision_required','missing price and VAT require decision');
$invalid=$c->calculate($base,['mode'=>'margin','value'=>100]);p_check(in_array('invalid_margin',$invalid['errors'],true),'invalid margin rejected');
echo $n.' pricing unit checks passed.'.PHP_EOL;

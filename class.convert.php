<?php
//////////////////////////////////////////////////////////////////////////////
//
// A super simple class file for super simple metric and imperial
// conversions and product price calculations. No frills!
//
//////////////////////////////////////////////////////////////////////////////

class Convert {

	public function __construct() {

	}
	
	// Inches to centimeters
	public function in2cm($in) {
		$cm = $in * 2.54;
		return round($cm, 3);
	}
	
	// Centimeters to inches
	public function cm2in($cm) {
		$in = $cm * 0.393700787;
		return round($in, 3);	
	}
	
	// Pounds to kilograms
	public function lb2kg($lb) {
		$kg = $lb * 0.45359237;
		return round($kg, 3);	
	}

	// Pounds to grams
	public function lb2gr($lb) {
		$gr = $lb * 453.59237;
		return round($gr, 3);	
	}
	
	// Pounds to ounces
	public function lb2oz($lb) {
		$oz = $lb * 16;
		return round($oz, 3);	
	}
	
	// Kilograms to pounds
	public function kg2lb($kg) {
		$lb = $kg * 2.20462262;
		return round($lb, 3);	
	}

	// Kilograms to ounces
	public function kg2oz($kg) {
		$oz = $kg * 35.2739619;
		return round($oz, 3);	
	}
	
	// Grams to ounces
	public function g2oz($g) {
		$oz = $g * 0.0352739619;
		return round($oz, 3);	
	}
	
	// Ounces to grams
	public function oz2g($oz) {
		$g = $oz * 28.3495231;
		return round($g, 3);	
	}
	
	// Ounces to pounds
	public function oz2lb($oz) {
		$lb = $oz * 0.0625;
		return round($lb, 3);	
	}
	
	public function box_price($case_price, $box_count) {
		$box_price = $case_price / $box_count;
		return number_format($box_price, 2);
	}
	
	public function unit_price($case_price, $unit_count) {
		$unit_price = $case_price / $unit_count;
		return number_format($unit_price, 2);
	}
	
	public function pallet_price($case_price, $count) {
		$pallet_price = $case_price * $count;
		return number_format($pallet_price, 2);
	}
}
?>
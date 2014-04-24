<?php
//////////////////////////////////////////////////////////////////////////////
//
// Class file for processing PVM formatted iDoc files exported from SAP
// http://help.sap.com/saphelp_nw04/helpdata/en/6f/1bd5baa85b11d6b28500508b5d5211/content.htm
// 
// iDocs are uploaded via FTP and made available to the application.
// Once selected their contents are confirmed, parsed, and imported into the application.
//
//////////////////////////////////////////////////////////////////////////////

class iDoc {	

	// Database and database handler
	private $db;
	private $dbh;
	
	// Database statement and results variables
	private $stmt;
	private $results;
	private $results_count;
	
	// Directories
	private $root_dir;
	private $incoming;
	private $outgoing;
	
	// Arrays
	public $idoc = array();
	public $caseData = array();
	public $boxData = array();
	public $unitData = array();
	public $palletData = array();
	public $happiness_array;
	public $barCodeArray = array();
	
	// iDoc Values
	public $description;
	public $SKU;
	public $SKURecord;
	public $product_id;
	public $case_id;
	public $case_price;
	public $club;
	
	public $box_denominator;
	public $unit_denominator;
	
	//////////////////////////////////////////////////////////////////////////////
	// Constructor	
	//////////////////////////////////////////////////////////////////////////////
	
	public function __construct($db) {
		// Set db and db handler via dependency injection
		$this->db = $db;
		$this->dbh = $this->db->getConnection();
		
		$this->root_dir = $_SERVER['DOCUMENT_ROOT'];
		$this->incoming = $this->root_dir . "\pvm-admin\idoc-incoming\\";
		$this->outgoing = $this->root_dir . "\pvm-admin\idoc-processed\\";
		
		// Happiness :)
		$this->happiness_array = array("Success","Ooooh yeah","Right on","Heck yeah","Excellent","Woot","Sweet","Hoooray","Yippeeee","Awesome");
	}
	
	// Returns the iDoc count from the incoming FTP directory
	public function getiDocCount() {
		if (glob($this->incoming . "*.idoc") != false) {
			$this->idoc_count = count(glob($this->incoming . "*.idoc"));
			return $this->idoc_count;
		} else {
			return 0;
		}
	}
	
	// Returns the processed iDoc count from the outgoing FTP directory
	public function getProcessediDocCount() {
		if (glob($this->outgoing . "*.idoc") != false) {
			$this->idoc_count = count(glob($this->outgoing . "*.idoc"));
			return $this->idoc_count;
		} else {
			return 0;
		}
	}
			
	// Returns the idocs array containing all the iDocs found 
	public function getiDocsList() {
  		$dir = opendir($this->incoming); 
		$idocs = array();
		while ($file = readdir($dir)) {
			$parts = explode(".", $file);
			if (is_array($parts) && count($parts) > 1) {    
        		$extension = end($parts);
				if ($extension == "idoc" OR $extension == "IDOC") {
					array_push($idocs, $file);
				}
			}
        }
		closedir($dir);
		
		return ($idocs);
	}

	// Returns the idocs array containing all the processed iDocs found
	public function getProcessediDocsList() {
  		$dir = opendir($this->outgoing); 
		$idocs = array();
		while ($file = readdir($dir)) {
			$parts = explode(".", $file);
			if (is_array($parts) && count($parts) > 1) {    
        		$extension = end($parts);
				if ($extension == "idoc" OR $extension == "IDOC") {
					array_push($idocs, $file);
				}
			}
        }
		closedir($dir);
		
		return ($idocs);
	}
		
	// Reads the selected iDoc and pushes it line by line into the idoc array 
	public function openiDoc($idoc) {
		$handle = @fopen($this->incoming . $idoc, "r");
		
		//Push the name of the idoc to the first row in the array
		array_push ($this->idoc, $idoc);
		
		if ($handle) {
    		while (($buffer = fgets($handle, 4096)) !== false) {
        		array_push ($this->idoc,$buffer);
    		}
			
    		if (!feof($handle)) {
				return false;
			}
    		
			fclose($handle);
			return true;		
		}
	}

	// Reads the selected processed iDoc and pushes it line by line into the idoc array 
	public function openProcessediDoc($idoc) {
		$handle = @fopen($this->outgoing . $idoc, "r");
		
		// Push the name of the idoc to the first row in the array
		array_push ($this->idoc, $idoc);
		
		if ($handle) {
    		while (($buffer = fgets($handle, 4096)) !== false) {
        		array_push ($this->idoc,$buffer);
    		}
			
    		if (!feof($handle)) {
				return false;
			}
    		
			fclose($handle);
			return true;		
		}
	}
	
	// Returns the idoc array
	public function getiDoc() {
		return $this->idoc;	
	}
	
	// Removes an iDoc
	public function removeiDoc($idoc) {
		$idoc_current = $this->incoming . $idoc;
		$idoc_dest = $this->outgoing . $idoc;
		
		if (copy ($idoc_current, $idoc_dest)) {
			unlink($idoc_current);
			return true;	
		} else {
			return false;	
		}
	}
	
	// Setter for club
	public function setClub($clubproduct) {
		if ($clubproduct == "yes") {
			$this->club = 1;
		} else {
			$this->club = 0;
		}
	}
	
	// Getter for club
	public function getClub() {
		return $this->club;	
	}
	
	// Random Happiness
	public function getHappiness() {
		$rand_key = array_rand($this->happiness_array);
		return $this->happiness_array[$rand_key];	
	}
	
	// Returns pricing information found in idoc array
	public function getPriceInfo() {
		$info = "Case Price: " . $this->case_price . ", Unit Denominator: " . $this->unit_denominator . ", Unit Price: " . $this->case_price / $this->unit_denominator;
		return $info;	
	}
	
	// Returns the description from the SKU Record
	public function getiDocDescription() {	
		foreach($this->idoc as $key => $value) {
			$pos = strpos($value,"E2MAKTM");
			if($pos === false) {
				//
			} else {
				$this->description = substr($this->idoc[$key], 67, 40);
				return $this->description;
			}
		}	
	}
	
	// Returns the entire SKU Record
	public function getSKURecord() {
		foreach($this->idoc as $key => $value) {
			$pos = strpos($value,"E2MARAM");
			if($pos === false) {
				//
			} else {
				$this->SKURecord = $this->idoc[$key];
				return $this->idoc[$key];
			}

		}		
	}
	
	// Returns the SKU from the SKU Record
	public function getSKU() {	
		foreach($this->idoc as $key => $value) {
			$pos = strpos($value,"E2MARAM");
			if($pos === false) {
				//
			} else {
				$this->SKU = ltrim(substr($this->idoc[$key], 66, 18), "0");
				return $this->SKU;
			}
		}		
	}

	public function checkInitProductDuplicate() {
		try {
			$this->getSKU();
			
			$this->stmt = $this->dbh->prepare("SELECT id FROM products WHERE sku=:sku");
			$this->stmt->bindParam(':sku', $this->SKU, PDO::PARAM_INT);
			$this->stmt->execute();
				
			$this->results = $this->stmt->fetchAll(PDO::FETCH_ASSOC);
			$this->results_count = count($this->results);
			
			if ($this->results_count > 0) { return true; } else { return false; };
				
		} catch(PDOException $e){
    		return $e->getMessage();
		}				
	}
		
	// Checks if the product is a duplicate
	public function checkProductDuplicate($club) {
		try {
			$this->getSKU();
			
			$this->stmt = $this->dbh->prepare("SELECT id FROM products WHERE sku=:sku AND club=:club");
			$this->stmt->bindParam(':sku', $this->SKU, PDO::PARAM_INT);
			$this->stmt->bindParam(':club', $club, PDO::PARAM_INT);
			$this->stmt->execute();
				
			$this->results = $this->stmt->fetchAll(PDO::FETCH_ASSOC);
			$this->results_count = count($this->results);
			
			if ($this->results_count > 0) { return true; } else { return false; };
				
		} catch(PDOException $e){
    		return $e->getMessage();
		}				
	}
	
	// Checks is a specific bit of product data is a duplicate (case, box, unit)
	public function checkDuplicate($type, $gtin, $club) {
		try {
			$this->stmt = $this->dbh->prepare("SELECT id FROM $type WHERE gtin=:gtin AND club=:club");
			$this->stmt->bindParam(':gtin', $gtin, PDO::PARAM_STR);
			$this->stmt->bindParam(':club', $club, PDO::PARAM_INT);
			$this->stmt->execute();
				
			$this->results = $this->stmt->fetchAll(PDO::FETCH_ASSOC);
			$this->results_count = count($this->results);
			
			if ($this->results_count > 0) { return true; } else { return false; };
				
		} catch(PDOException $e){
    		return $e->getMessage();
		}		
	}
	
	public function checkClub($type, $id) {
		try {			
			$this->stmt = $this->dbh->prepare("SELECT club FROM $type WHERE id=:id");
			$this->stmt->bindParam(':id', $id, PDO::PARAM_INT);
			$this->stmt->execute();
				
			$this->results = $this->stmt->fetch(PDO::FETCH_ASSOC);
			
			return $this->results["club"];
							
		} catch(PDOException $e){
    		return $e->getMessage();
		}		
	}
		
	// Method for searching the idoc array for specific values, returning array positions
	public function findData($type) {
		$results = array();
		foreach($this->idoc as $key => $value) {
			$pos = strpos($value,"E2MARMM");
			if($pos === false) {
				//
			} else {
				if (trim(substr($value,66,3)) == $type) {
					$results[] = $key;
				}		
			}
		}
		return $results;
	}
	
	// Returns product ID based on SKU
	public function getProductID() {
		try {			
			$this->stmt = $this->dbh->prepare("SELECT id FROM products WHERE sku=:sku");
			$this->stmt->bindParam(':sku', $this->SKU, PDO::PARAM_INT);
			$this->stmt->execute();
				
			$this->results = $this->stmt->fetch(PDO::FETCH_ASSOC);

			return $this->results["id"];
							
		} catch(PDOException $e){
    		return $e->getMessage();
		}			
	}

	// Returns product ID based on SKU and Club
	public function getClubProductID($club) {
		try {			
			$this->stmt = $this->dbh->prepare("SELECT id FROM products WHERE sku=:sku AND club=:club");
			$this->stmt->bindParam(':sku', $this->SKU, PDO::PARAM_INT);
			$this->stmt->bindParam(':club', $club, PDO::PARAM_INT);
			$this->stmt->execute();
				
			$this->results = $this->stmt->fetch(PDO::FETCH_ASSOC);

			return $this->results["id"];
							
		} catch(PDOException $e){
    		return $e->getMessage();
		}			
	}
	
	// Returns id of case, box, unit
	public function getID($type, $gtin) {
		try {			
			$this->stmt = $this->dbh->prepare("SELECT id FROM $type WHERE gtin=:gtin");
			$this->stmt->bindParam(':gtin', $gtin, PDO::PARAM_STR);
			$this->stmt->execute();
				
			$this->results = $this->stmt->fetch(PDO::FETCH_ASSOC);

			return $this->results["id"];
							
		} catch(PDOException $e){
    		return $e->getMessage();
		}			
	}

	// Returns id of club case, box, unit
	public function getClubID($type, $gtin, $club) {
		try {			
			$this->stmt = $this->dbh->prepare("SELECT id FROM $type WHERE gtin=:gtin AND club=:club");
			$this->stmt->bindParam(':gtin', $gtin, PDO::PARAM_STR);
			$this->stmt->bindParam(':club', $club, PDO::PARAM_INT);
			$this->stmt->execute();
				
			$this->results = $this->stmt->fetch(PDO::FETCH_ASSOC);

			return $this->results["id"];
							
		} catch(PDOException $e){
    		return $e->getMessage();
		}			
	}
				
	// Updates box usage table with product id and box id
	public function updateBoxUsage($product_id, $box_id) {
		
		try {
			$this->stmt = $this->dbh->prepare("SELECT * FROM box_usage WHERE product_id=:product_id AND box_id=:box_id");
			$this->stmt->bindParam(':product_id', $product_id, PDO::PARAM_INT);
			$this->stmt->bindParam(':box_id', $box_id, PDO::PARAM_INT);
			$this->stmt->execute();
			
			$this->results = $this->stmt->fetchAll(PDO::FETCH_ASSOC);
			$this->results_count = count($this->results);		
			
			if ($this->results_count == 0) {
				$this->stmt = $this->dbh->prepare("INSERT INTO box_usage (product_id,box_id) VALUES (:product_id,:box_id)");
				$this->stmt->bindParam(':product_id', $product_id, PDO::PARAM_INT);
				$this->stmt->bindParam(':box_id', $box_id, PDO::PARAM_INT);
				$this->stmt->execute();
			}
								
		} catch(PDOException $e){
    		return $e->getMessage();
		}
	}

	// Updates unit usage table with product id and unit id
	public function updateUnitUsage($product_id, $unit_id) {
		try {
			$this->stmt = $this->dbh->prepare("SELECT * FROM unit_usage WHERE product_id=:product_id AND unit_id=:unit_id");
			$this->stmt->bindParam(':product_id', $product_id, PDO::PARAM_INT);
			$this->stmt->bindParam(':unit_id', $unit_id, PDO::PARAM_INT);
			$this->stmt->execute();
			
			$this->results = $this->stmt->fetchAll(PDO::FETCH_ASSOC);
			$this->results_count = count($this->results);
			
			if ($this->results_count == 0) {				
				$this->stmt = $this->dbh->prepare("INSERT INTO unit_usage (product_id,unit_id) VALUES (:product_id,:unit_id)");
				$this->stmt->bindParam(':product_id', $product_id, PDO::PARAM_INT);
				$this->stmt->bindParam(':unit_id', $unit_id, PDO::PARAM_INT);
				$this->stmt->execute();
			}
								
		} catch(PDOException $e){
    		return $e->getMessage();
		}
	}
	
	// Adds GTIN to barCodeArray for later processing
	public function updateBarCodeArray($gtin) {
		$this->barCodeArray[] = $gtin;
	}
	
	// Returns barCodeArray for rendering
	public function getBarCodeArray() {
		return $this->barCodeArray;	
	}
	
	//////////////////////////////////////////////////////////////////////////////
	// Main iDoc Parser
	//////////////////////////////////////////////////////////////////////////////
			
	public function parseiDoc() {
		$parse_results = "";
		
		$this->getSKURecord();
		
		$this->getSKU();
		$this->getiDocDescription();
		
		// Update case, box, unit and pallet arrays
		$this->caseData = $this->findData("CA");
		$this->boxData = $this->findData("BX");
		$this->unitData = $this->findData("EA");
		$this->palletData = $this->findData("PL");
		
		// Extract SKU values and write to products table
		$gross_weight = substr($this->SKURecord,314,14);
		$net_weight = substr($this->SKURecord,328,14);
		$weight_uom = substr($this->SKURecord,342,3);
		$ti = ltrim(substr($this->SKURecord,762,5), "0");
		$hi = ltrim(substr($this->SKURecord,766,5), "0");
		$date_created = strtotime("now");
		$zero = 0;
		
		$product_description = $this->SKU . " - " . $this->description;
		
		$duplicate_product = $this->checkProductDuplicate($this->club);
		
		if (!$duplicate_product) {
			//Product is not a duplicate
			try {
				$this->stmt = $this->dbh->prepare("INSERT INTO products (sku,gross_weight,net_weight,weight_uom,ti,hi,date_created,description,club,published,oneoff) VALUES (:sku,:gross_weight,:net_weight,:weight_uom,:ti,:hi,:date_created,:description,:club,:published,:oneoff)");
				$this->stmt->bindParam(':sku', $this->SKU, PDO::PARAM_INT);
				$this->stmt->bindParam(':gross_weight', $gross_weight, PDO::PARAM_STR);
				$this->stmt->bindParam(':net_weight', $net_weight, PDO::PARAM_STR);
				$this->stmt->bindParam(':weight_uom', $weight_uom, PDO::PARAM_STR);
				$this->stmt->bindParam(':ti', $ti, PDO::PARAM_INT);
				$this->stmt->bindParam(':hi', $hi, PDO::PARAM_INT);
				$this->stmt->bindParam(':date_created', $date_created, PDO::PARAM_INT);
				$this->stmt->bindParam(':description', $product_description, PDO::PARAM_STR);
				$this->stmt->bindParam(':club', $this->club, PDO::PARAM_INT);
				$this->stmt->bindParam(':published', $zero, PDO::PARAM_INT);
				$this->stmt->bindParam(':oneoff', $zero, PDO::PARAM_INT);
				$this->stmt->execute();
												
			} catch(PDOException $e){
				return $e->getMessage();
			}
			
			$parse_results .= "1- Added New ";
			
		} else {
			$this->product_id = $this->getClubProductID($this->club);
			
			try {
				$this->stmt = $this->dbh->prepare("UPDATE products SET sku=:sku, gross_weight=:gross_weight, net_weight=:net_weight, weight_uom=:weight_uom, ti=:ti, hi=:hi, description=:description WHERE id=:id");
				$this->stmt->bindParam(':sku', $this->SKU, PDO::PARAM_INT);
				$this->stmt->bindParam(':gross_weight', $gross_weight, PDO::PARAM_STR);
				$this->stmt->bindParam(':net_weight', $net_weight, PDO::PARAM_STR);
				$this->stmt->bindParam(':weight_uom', $weight_uom, PDO::PARAM_STR);
				$this->stmt->bindParam(':ti', $ti, PDO::PARAM_INT);
				$this->stmt->bindParam(':hi', $hi, PDO::PARAM_INT);
				$this->stmt->bindParam(':description', $product_description, PDO::PARAM_STR);
				$this->stmt->bindParam(':id', $this->product_id, PDO::PARAM_INT);
				$this->stmt->execute();												
			} catch(PDOException $e){
				return $e->getMessage();
			}
			
			$parse_results .= "2- Updated ";		
		} //end duplicate else
		
		//Get product ID
		$this->product_id = $this->getClubProductID($this->club);
		
		$parse_results .= "main product sheet information to database... Product ID: " . $this->product_id . "</p>\n";
				
		//////////////////////////////////////////////////////////////////////////////
		//Parse Case Array
		//////////////////////////////////////////////////////////////////////////////		
		$parse_results .= "<p>";
		
		foreach ($this->caseData as $value) {
			$gtin = rtrim(substr($this->idoc[$value],79,18), " ");
			$duplicate_gtin = $this->checkDuplicate("cases", $gtin, $this->club);
			
			$this->case_price = trim(substr($this->idoc[$value+1],102,12));

			$length = substr($this->idoc[$value],99,14);
			$width = substr($this->idoc[$value],113,14);
			$height = substr($this->idoc[$value],127,14);
			$dimension_uom = substr($this->idoc[$value],141,3);
			$volume = substr($this->idoc[$value],144,14);
			$volume_uom = substr($this->idoc[$value],158,3);
			$weight = substr($this->idoc[$value],161,14);
			$weight_uom = substr($this->idoc[$value],175,3);
			
			$price = $this->case_price;
							
			if (!$duplicate_gtin) {
				
				//Write case data to table
				try {
					$this->stmt = $this->dbh->prepare("INSERT INTO cases (gtin,length,width,height,dimension_uom,volume,volume_uom,weight,weight_uom,price,club) VALUES (:gtin,:length,:width,:height,:dimension_uom,:volume,:volume_uom,:weight,:weight_uom,:price,:club)");
					$this->stmt->bindParam(':gtin', $gtin, PDO::PARAM_STR);
					$this->stmt->bindParam(':length', $length, PDO::PARAM_STR);
					$this->stmt->bindParam(':width', $width, PDO::PARAM_STR);
					$this->stmt->bindParam(':height', $height, PDO::PARAM_STR);
					$this->stmt->bindParam(':dimension_uom', $dimension_uom, PDO::PARAM_STR);
					$this->stmt->bindParam(':volume', $volume, PDO::PARAM_STR);
					$this->stmt->bindParam(':volume_uom', $volume_uom, PDO::PARAM_STR);
					$this->stmt->bindParam(':weight', $weight, PDO::PARAM_STR);
					$this->stmt->bindParam(':weight_uom', $weight_uom, PDO::PARAM_STR);
					$this->stmt->bindParam(':price', $price, PDO::PARAM_STR);
					$this->stmt->bindParam(':club', $this->club, PDO::PARAM_INT);
					$this->stmt->execute();
								
				} catch(PDOException $e){
    				return $e->getMessage();
				}
								
				//Update Barcode Array
				$this->updateBarCodeArray($gtin);
				
				//Update Product table
				$this->case_id = $this->getClubID("cases", $gtin, $this->club);				
				$parse_results .= "Case ID: " . $this->case_id . ". ";
				
				try {
					$this->stmt = $this->dbh->prepare("UPDATE products SET case_id=:case_id WHERE id=:product_id");
					$this->stmt->bindParam(':case_id', $this->case_id, PDO::PARAM_INT);
					$this->stmt->bindParam(':product_id', $this->product_id, PDO::PARAM_INT);
					$this->stmt->execute();
								
				} catch(PDOException $e){
    				return $e->getMessage();
				}
				
				$parse_results .= "1- Added case GTIN: " . $gtin . ". ";
										
			} else {
				//Case is a duplicate				
				$this->case_id = $this->getClubID("cases", $gtin, $this->club);				
				try {
					$this->stmt = $this->dbh->prepare("UPDATE cases SET gtin=:gtin, length=:length, width=:width, height=:height, dimension_uom=:dimension_uom, volume=:volume, volume_uom=:volume_uom, weight=:weight, weight_uom=:weight_uom, price=:price WHERE id=:id");
					$this->stmt->bindParam(':gtin', $gtin, PDO::PARAM_STR);
					$this->stmt->bindParam(':length', $length, PDO::PARAM_STR);
					$this->stmt->bindParam(':width', $width, PDO::PARAM_STR);
					$this->stmt->bindParam(':height', $height, PDO::PARAM_STR);
					$this->stmt->bindParam(':dimension_uom', $dimension_uom, PDO::PARAM_STR);
					$this->stmt->bindParam(':volume', $volume, PDO::PARAM_STR);
					$this->stmt->bindParam(':volume_uom', $volume_uom, PDO::PARAM_STR);
					$this->stmt->bindParam(':weight', $weight, PDO::PARAM_STR);
					$this->stmt->bindParam(':weight_uom', $weight_uom, PDO::PARAM_STR);
					$this->stmt->bindParam(':price', $price, PDO::PARAM_STR);
					$this->stmt->bindParam(':id', $this->case_id, PDO::PARAM_INT);
					
					$this->stmt->execute();
								
				} catch(PDOException $e){
					return $e->getMessage();
				}
				
				//Update Product table
				try {
					$this->stmt = $this->dbh->prepare("UPDATE products SET case_id=:case_id WHERE id=:product_id");
					$this->stmt->bindParam(':case_id', $this->case_id, PDO::PARAM_INT);
					$this->stmt->bindParam(':product_id', $this->product_id, PDO::PARAM_INT);
					$this->stmt->execute();
						
				} catch(PDOException $e){
					return $e->getMessage();
				}
			
				$parse_results .= "2- Updated case GTIN: " . $gtin . ". ";		
			}
		} //End foreach
		
		$parse_results .= "&nbsp;&nbsp;<strong>" . $this->getHappiness() . "!</strong></p>";
		
		//////////////////////////////////////////////////////////////////////////////
		//Parse Box Array
		//////////////////////////////////////////////////////////////////////////////
		$parse_results .= "<p>";

		foreach ($this->boxData as $value) {
			// Add up each box conversion denominator to get the total amount
			$this->box_denominator += (str_replace(" ", "", (substr($this->idoc[$value],74,5))) + 0);
		}
				
		foreach ($this->boxData as $value) {
			$gtin = rtrim(substr(substr($this->idoc[$value],79,18), 2), " ");
			$duplicate_gtin = $this->checkDuplicate("boxes", $gtin, $this->club);
			
			$length = substr($this->idoc[$value],99,14);
			$width = substr($this->idoc[$value],113,14);
			$height = substr($this->idoc[$value],127,14);
			$dimension_uom = substr($this->idoc[$value],141,3);
			$volume = substr($this->idoc[$value],144,14);
			$volume_uom = substr($this->idoc[$value],158,3);
			
			if (strpos($this->idoc[$value+1], "ZE1MARMM000") !== false) {
				$weight = substr($this->idoc[$value+1],81,15);
				$weight_uom = trim(substr($this->idoc[$value+1],95,6));
				$flavor = trim(substr($this->idoc[$value+1],63,18));
			} else {
				$weight = "";
				$weight_uom = "";
				$flavor = "";
			}
			
			$price = $this->case_price / $this->box_denominator;
			
			if ($flavor == "PURE FRESH SPEARMI") {
				$flavor = "PURE FRESH SPEARMINT";
			}

			//Check weight and if in pounds, convert to ounces
			if ($weight_uom == "LBS" || $weight_uom == "LB") {
				$weight_uom = "OZ";
				$weight = $weight * 16;
			}
			
			if (!$duplicate_gtin) {				
				//Write box data to table
				try {
					$this->stmt = $this->dbh->prepare("INSERT INTO boxes (gtin,length,width,height,dimension_uom,volume,volume_uom,weight,weight_uom,flavor,price,club) VALUES (:gtin,:length,:width,:height,:dimension_uom,:volume,:volume_uom,:weight,:weight_uom,:flavor,:price,:club)");
					$this->stmt->bindParam(':gtin', $gtin, PDO::PARAM_STR);
					$this->stmt->bindParam(':length', $length, PDO::PARAM_STR);
					$this->stmt->bindParam(':width', $width, PDO::PARAM_STR);
					$this->stmt->bindParam(':height', $height, PDO::PARAM_STR);
					$this->stmt->bindParam(':dimension_uom', $dimension_uom, PDO::PARAM_STR);
					$this->stmt->bindParam(':volume', $volume, PDO::PARAM_STR);
					$this->stmt->bindParam(':volume_uom', $volume_uom, PDO::PARAM_STR);
					$this->stmt->bindParam(':weight', $weight, PDO::PARAM_STR);
					$this->stmt->bindParam(':weight_uom', $weight_uom, PDO::PARAM_STR);
					$this->stmt->bindParam(':flavor', $flavor, PDO::PARAM_STR);
					$this->stmt->bindParam(':price', $price, PDO::PARAM_STR);
					$this->stmt->bindParam(':club', $this->club, PDO::PARAM_STR);
					
					$this->stmt->execute();
								
				} catch(PDOException $e){
    				return $e->getMessage();
				}
								
				//Update Barcode Array
				$this->updateBarCodeArray($gtin);
				
				//Update Box Usage Table
				$box_id = $this->getClubID("boxes", $gtin, $this->club);
				$this->updateBoxUsage($this->product_id, $box_id);				
				
				if ($this->club) {
					$parse_results .= "1- Added new CLUB box GTIN: " . $gtin . ". ";
				} else {
					$parse_results .= "1- Added new box GTIN: " . $gtin . ". ";
				}
											
			} else {
				//Box is a duplicate, check Club values
				$box_id = $this->getClubID("boxes", $gtin, $this->club);
								
				try {
					$this->stmt = $this->dbh->prepare("UPDATE boxes SET gtin=:gtin, length=:length, width=:width, height=:height, dimension_uom=:dimension_uom, volume=:volume, volume_uom=:volume_uom, weight=:weight, weight_uom=:weight_uom, flavor=:flavor, price=:price WHERE id=:id");
					$this->stmt->bindParam(':gtin', $gtin, PDO::PARAM_STR);
					$this->stmt->bindParam(':length', $length, PDO::PARAM_STR);
					$this->stmt->bindParam(':width', $width, PDO::PARAM_STR);
					$this->stmt->bindParam(':height', $height, PDO::PARAM_STR);
					$this->stmt->bindParam(':dimension_uom', $dimension_uom, PDO::PARAM_STR);
					$this->stmt->bindParam(':volume', $volume, PDO::PARAM_STR);
					$this->stmt->bindParam(':volume_uom', $volume_uom, PDO::PARAM_STR);
					$this->stmt->bindParam(':weight', $weight, PDO::PARAM_STR);
					$this->stmt->bindParam(':weight_uom', $weight_uom, PDO::PARAM_STR);
					$this->stmt->bindParam(':flavor', $flavor, PDO::PARAM_STR);
					$this->stmt->bindParam(':price', $price, PDO::PARAM_STR);
					$this->stmt->bindParam(':id', $box_id, PDO::PARAM_INT);
					
					$this->stmt->execute();
								
				} catch(PDOException $e){
					return $e->getMessage();
				}
				
				//Update Box Usage Table
				$this->updateBoxUsage($this->product_id, $box_id);				
				$parse_results .= "2- Updated box GTIN: " . $gtin . ". Box ID: " . $box_id . ", Product ID: " . $this->product_id . ", Results Count: " . $this->results_count;
			} 
		} 
				
		$parse_results .= "&nbsp;&nbsp;<strong>" . $this->getHappiness() . "!</strong></p>";
				
		//////////////////////////////////////////////////////////////////////////////
		//Parse Unit Array
		//////////////////////////////////////////////////////////////////////////////
		$parse_results .= "<p>";
		
		foreach ($this->unitData as $value) {
			// Add up each unit conversion denominator to get the total amount
			$this->unit_denominator += (str_replace(" ", "", (substr($this->idoc[$value],74,5))) + 0);
		}
		
		foreach ($this->unitData as $value) {
			$gtin = rtrim(substr(substr($this->idoc[$value],79,18), 2), " ");
			$duplicate_gtin = $this->checkDuplicate("units", $gtin, $this->club);

			$length = substr($this->idoc[$value],99,14);
			$width = substr($this->idoc[$value],113,14);
			$height = substr($this->idoc[$value],127,14);
			$dimension_uom = substr($this->idoc[$value],141,3);
			$volume = substr($this->idoc[$value],144,14);
			$volume_uom = substr($this->idoc[$value],158,3);
			
			if (strpos($this->idoc[$value+1], "ZE1MARMM000") !== false) {
				$weight = substr($this->idoc[$value+1],81,15);
				$weight_uom = trim(substr($this->idoc[$value+1],95,6));
				$flavor = trim(substr($this->idoc[$value+1],63,18));
			} else {
				$weight = "";
				$weight_uom = "";
				$flavor = "";				
			}
			
			if ($flavor == "PURE FRESH SPEARMI") {
				$flavor = "PURE FRESH SPEARMINT";
			}

			$price = $this->case_price / $this->unit_denominator;
			
			//Check weight and if in pounds, convert to ounces
			if ($weight_uom == "LBS" || $weight_uom == "LB") {
				$weight_uom = "OZ";
				$weight = $weight * 16;
			}
							
			if (!$duplicate_gtin && !$gtin == "" && strlen($gtin)>9) {
				//Write unit data to table
				try {
					$this->stmt = $this->dbh->prepare("INSERT INTO units (gtin,length,width,height,dimension_uom,volume,volume_uom,weight,weight_uom,flavor,price,club) VALUES (:gtin,:length,:width,:height,:dimension_uom,:volume,:volume_uom,:weight,:weight_uom,:flavor,:price,:club)");
					$this->stmt->bindParam(':gtin', $gtin, PDO::PARAM_STR);
					$this->stmt->bindParam(':length', $length, PDO::PARAM_STR);
					$this->stmt->bindParam(':width', $width, PDO::PARAM_STR);
					$this->stmt->bindParam(':height', $height, PDO::PARAM_STR);
					$this->stmt->bindParam(':dimension_uom', $dimension_uom, PDO::PARAM_STR);
					$this->stmt->bindParam(':volume', $volume, PDO::PARAM_STR);
					$this->stmt->bindParam(':volume_uom', $volume_uom, PDO::PARAM_STR);
					$this->stmt->bindParam(':weight', $weight, PDO::PARAM_STR);
					$this->stmt->bindParam(':weight_uom', $weight_uom, PDO::PARAM_STR);
					$this->stmt->bindParam(':flavor', $flavor, PDO::PARAM_STR);
					$this->stmt->bindParam(':price', $price, PDO::PARAM_STR);
					$this->stmt->bindParam(':club', $this->club, PDO::PARAM_INT);
					
					$this->stmt->execute();
								
				} catch(PDOException $e){
    				return $e->getMessage();
				}
								
				//Update Barcode Array
				$this->updateBarCodeArray($gtin);
				
				//Update Unit Usage Table
				$unit_id = $this->getClubID("units", $gtin, $this->club);
				$this->updateUnitUsage($this->product_id, $unit_id);				
				
				if ($this->club) {
					$parse_results .= "1- Added new CLUB unit GTIN: " . $gtin;
				} else {
					$parse_results .= "1- Added new unit GTIN: " . $gtin;
				}
											
			} else {
				//Unit is a duplicate, check Club values				
				$unit_id = $this->getClubID("units", $gtin, $this->club);
					
				//Club matches, do an update					
				try {
					$this->stmt = $this->dbh->prepare("UPDATE units SET gtin=:gtin, length=:length, width=:width, height=:height, dimension_uom=:dimension_uom, volume=:volume, volume_uom=:volume_uom, weight=:weight, weight_uom=:weight_uom, flavor=:flavor, price=:price WHERE id=:id");
					$this->stmt->bindParam(':gtin', $gtin, PDO::PARAM_STR);
					$this->stmt->bindParam(':length', $length, PDO::PARAM_STR);
					$this->stmt->bindParam(':width', $width, PDO::PARAM_STR);
					$this->stmt->bindParam(':height', $height, PDO::PARAM_STR);
					$this->stmt->bindParam(':dimension_uom', $dimension_uom, PDO::PARAM_STR);
					$this->stmt->bindParam(':volume', $volume, PDO::PARAM_STR);
					$this->stmt->bindParam(':volume_uom', $volume_uom, PDO::PARAM_STR);
					$this->stmt->bindParam(':weight', $weight, PDO::PARAM_STR);
					$this->stmt->bindParam(':weight_uom', $weight_uom, PDO::PARAM_STR);
					$this->stmt->bindParam(':flavor', $flavor, PDO::PARAM_STR);
					$this->stmt->bindParam(':price', $price, PDO::PARAM_STR);
					$this->stmt->bindParam(':id', $unit_id, PDO::PARAM_INT);
					
					$this->stmt->execute();
								
				} catch(PDOException $e){
					return $e->getMessage();
				}
				
				//Update Unit Usage Table				
				$this->updateUnitUsage($this->product_id, $unit_id);				
				$parse_results .= "2- Updated unit GTIN: " . $gtin . ". Unit ID: " . $unit_id . ", Product ID: " . $this->product_id . ", Results Count: " . $this->results_count;				
										
			} //end duplicate else
		} //end foreach
		
		$parse_results .= "&nbsp;&nbsp;<strong>" . $this->getHappiness() . "!</strong></p>";
		
		//////////////////////////////////////////////////////////////////////////////
		//Parse Pallet Array
		//////////////////////////////////////////////////////////////////////////////		
		
		foreach ($this->palletData as $value) {

			$length = trim(substr($this->idoc[$value],99,14));
			$width = trim(substr($this->idoc[$value],113,14));
			$height = trim(substr($this->idoc[$value],127,14));
			
			try {
				$this->stmt = $this->dbh->prepare("UPDATE products SET pallet_length=:pallet_length, pallet_width=:pallet_width, pallet_height=:pallet_height WHERE id=:product_id");
				$this->stmt->bindParam(':pallet_length', $length, PDO::PARAM_STR);
				$this->stmt->bindParam(':pallet_width', $width, PDO::PARAM_STR);
				$this->stmt->bindParam(':pallet_height', $height, PDO::PARAM_STR);
				$this->stmt->bindParam(':product_id', $this->product_id, PDO::PARAM_INT);
				$this->stmt->execute();
							
			} catch(PDOException $e){
				return $e->getMessage();
			}
			
			$parse_results .= "Saved pallet dimensions. ";
		
		}
		
		//This is end.... my only friend...	
						
		return $parse_results;
	}
}
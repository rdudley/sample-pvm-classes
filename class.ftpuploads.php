<?php
//////////////////////////////////////////////////////////////////////////////
//
// Class file for handling administrative FTP uploads for import into
// the PVM Document Management system.
// 
//////////////////////////////////////////////////////////////////////////////

class FTPUploads {
	
	// Database and database handler
	private $db;
	private $dbh;
	private $stmt;
	
	// Thumbnail generator
	private $tg;
	
	// Directory Path
	private $ftp_directory_path;
	
	// Admin ID
	public $admin_id;
	
	// Valid File Type Array
	private $valid_file_types;
	
	// File Arrays
	public $files;	
	public $valid_files;
	public $invalid_files;
	
	// File Counts
	public $valid_file_count;
	public $invalid_file_count;
	
	// Mime-type and file extension variables
	public $mimetype; 
	public $extension; 
	
	//////////////////////////////////////////////////////////////////////////////
	// Constructor	
	//////////////////////////////////////////////////////////////////////////////
	
	public function __construct() {
		$this->ftp_directory_path = $_SERVER['DOCUMENT_ROOT'] . "/ftp-uploads";
		$this->setValidFileTypes();
		$this->getFilesFromDir($this->ftp_directory_path);
	}
	
	// Sets valid_file_types array
	private function setValidFileTypes() {
		$this->valid_file_types = array(
			"application/pdf",
			"application/msword",
			"application/postscript",
			"application/vnd.ms-excel",
			"application/vnd.ms-powerpoint",
			"application/vnd.ms-office",			
			"application/vnd.openxmlformats-officedocument.presentationml.presentation",
			"application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
			"application/vnd.openxmlformats-officedocument.wordprocessingml.document",
			"application/vnd.ms-excel.sheet.macroEnabled.12",
			"image/gif",
			"image/jpeg",
			"image/png",
			"image/tiff",
			"text/plain",
			"image/vnd.adobe.photoshop",
			"video/quicktime",
			"video/x-ms-wmv",
			"video/x-ms-asf",
			"video/mp4",
			"video/x-m4v",
			"application/zip",
			"application/octet-stream"
		);	
	}
	
	// Database instance and database handler
	public function setDB($db) {
		$this->db = $db;
		$this->dbh = $this->db->getConnection();		
	}
	
	// Thumbnail generator
	public function setTG($tg) {
		$this->tg = $tg;	
	}
	
	// Setter for admin_id
	public function setAdminID($id) {
		$this->admin_id = $id;	
	}
	
	// Main method for recursively reading files and directories
	private function getFilesFromDir($dir) {
		$this->files = array();
		if ($handle = opendir($dir)) {
			while (false !== ($file = readdir($handle))) {
				if ($file != "." && $file != "..") {
					if(is_dir($dir.'/'.$file)) {
						$dir2 = $dir.'/'.$file;
						$this->files[] = $this->getFilesFromDir($dir2);
					} else {
						$this->files[] = $file;
					}
				}
			}
    		closedir($handle);
		}
		return $this->array_flat($this->files);
	}
	
	// Returns the files array
	public function showFiles() {
		return $this->files;	
	}
	
	// Returns the count of valid files
	public function getValidFileCount() {
		return $this->valid_file_count;
	}	

	// Returns the count of valid files
	public function getInvalidFileCount() {
		return $this->invalid_file_count;
	}
	
	// Returns the valid files array
	public function getValidFiles() {
		return $this->valid_files;
	}	

	// Returns the invalid files array
	public function getInvalidFiles() {
		return $this->invalid_files;
	}
	
	// Validates every file in the files array using fileValid() method, updating valid or invalid file arrays, 
	// processing valid files and removing invalid files
	public function validateFiles() {
		$this->valid_file_count = 0;
		$this->invalid_file_count = 0;
		
		foreach($this->files as $file) {
			if ($this->fileValid($file)) {
				$this->valid_files[] = $file;
				$this->valid_file_count++;
				$this->processFile($file);
			} else {
				$this->invalid_files[] = $file;
				$this->invalid_file_count++;
				$this->deleteFile($file);
			}
		}
		
		return "<p>&nbsp;</p><p>Documents have been processed. You can find them in the<br /><strong>FTP Uploads</strong> folder in Document Management.</p>";
	}

	// Validates every file in the files array using fileValid(), only updating valid or invalid file arrays
	public function checkFiles() {
		$this->valid_file_count = 0;
		$this->invalid_file_count = 0;
		
		foreach($this->files as $file) {
			if ($this->fileValid($file)) {
				$this->valid_files[] = $file;
				$this->valid_file_count++;
			} else {
				$this->invalid_files[] = $file;
				$this->invalid_file_count++;
			}
		}
	}
		
	// Checks finfo_open(FILEINFO_MIME_TYPE) valid_file_types array,
	// then performs additional checks on octet-stream and zip mimetypes
	private function fileValid($file) {
		$current_file = $this->ftp_directory_path . "/" . $file;
		
		$file_valid = true;
		
		$finfo = finfo_open(FILEINFO_MIME_TYPE);
		$this->mimetype = finfo_file($finfo, $current_file);
		$this->extension = pathinfo($current_file, PATHINFO_EXTENSION);

		if (!in_array($this->mimetype, $this->valid_file_types)) {
			$file_valid = false;
		}
	
		// Additional check against application/zip for MS Office documents
		if ($this->mimetype == "application/zip") {
			if ($this->extension == "docx" || $this->extension == "xlsx" || $this->extension == "xlsm" || $this->extension == "pptx") {
				$file_valid = true;
			} else {
				$file_valid = false;
			}
		}
		
		// Additional check against application/octet-stream since WMV is a pain in the arse
		if ($this->mimetype == "application/octet-stream") {
			if ($this->extension == "wmv") {
				$this->mimetype = "video/x-ms-wmv";
				$file_valid = true;
			} elseif ($this->extension == "eps") {
				$this->mimetype = "application/octet-stream";
				$file_valid = true;			
			} elseif ($this->extension == "wrf") {
				$this->mimetype = "application/octet-stream";
				$file_valid = true;			
			} elseif ($this->extension == "mov") {
				$this->mimetype = "video/quicktime";
				$file_valid = true;			
			} else {
				$file_valid = false;
			}
		}				
		return $file_valid;		
	}

	// Deletes a files
	private function deleteFile($file) {
		unlink($this->ftp_directory_path . "/" . $file);	
	}
	
	public function getTotalDocumentCount() {
		try {
			$this->stmt = $this->dbh->prepare("SELECT document_id FROM documents");
			$this->stmt->execute();
			
			return count($this->stmt->fetchAll());
			
		} catch(PDOException $e){
    		echo $e->getMessage();
		}		
	}
	
	// Takes a valid file, checks the filename, and adds to Document Management
	public function processFile($file) {	
		$safeName = $this->sanitize_file_name($file);		
		rename ($this->ftp_directory_path . "/" . $file, $this->ftp_directory_path . "/" . $safeName);
		
		$filesize = filesize($this->ftp_directory_path . "/" . $file);	
		$current_time = strtotime("now");
		$one = 1;
		$zero = 0;
		$category = 722;
			
		// Check to make sure the file doesn't currently exist. If so, adjust the name to prevent duplicates
		if (file_exists($_SERVER['DOCUMENT_ROOT'] . "/documents/" . $safeName)) {
			$now = strtotime("now");
			rename ($this->ftp_directory_path . "/" . $safeName, $this->ftp_directory_path . "/" . $now . "-" . $safeName);
			$safeName = $now . "-" . $safeName;
		}
		
		// Write to database	
		try {
	
		$this->stmt = $this->dbh->prepare("INSERT INTO documents (document_name, document_filename, document_type, document_size, document_category_id,document_created_by,document_created_timestamp,document_visible,document_timed) VALUES (:document_name,:document_filename,:document_type,:document_size,:document_category_id,:document_created_by,:document_created_timestamp,:document_visible,:document_timed)");
		
		/*** bind the paramaters ***/
		$this->stmt->bindParam(':document_name', $safeName, PDO::PARAM_STR);
		$this->stmt->bindParam(':document_filename', $safeName, PDO::PARAM_STR);
		$this->stmt->bindParam(':document_type', $this->mimetype, PDO::PARAM_STR);
   		$this->stmt->bindParam(':document_size', $filesize, PDO::PARAM_INT);
    	$this->stmt->bindParam(':document_category_id', $category, PDO::PARAM_INT);
		$this->stmt->bindParam(':document_created_by', $this->admin_id, PDO::PARAM_INT);
		$this->stmt->bindParam(':document_created_timestamp', $current_time);
		$this->stmt->bindParam(':document_visible', $one);
		$this->stmt->bindParam(':document_timed', $zero);
    
		/*** execute the prepared statement ***/
    	$this->stmt->execute();
				
		} catch(PDOException $e){
    		echo $e->getMessage();
		}

		// Generate a small and large thumbnail of the image
		if ($this->mimetype == "image/gif" || $this->mimetype == "image/jpeg" || $this->mimetype == "image/png") {
			$this->tg->init($this->ftp_directory_path . "/". $safeName, $this->ftp_directory_path . "/". "thumb-$safeName", $this->mimetype);
			$this->tg->generateSmall();
			
			$thumbnail = $_SERVER['DOCUMENT_ROOT'] . "/ftp-uploads/thumb-" . $safeName;
			$thumbnail_dest = $_SERVER['DOCUMENT_ROOT'] . "/thumbnails/thumb-" . $safeName;

			$this->tg->init($this->ftp_directory_path . "/". $safeName, $this->ftp_directory_path . "/". "thumb-lg-$safeName", $this->mimetype);
			$this->tg->generateLarge();
			$thumbnail_lg = $_SERVER['DOCUMENT_ROOT'] . "/ftp-uploads/thumb-lg-" . $safeName;
			$thumbnail_lg_dest = $_SERVER['DOCUMENT_ROOT'] . "/thumbnails/thumb-lg-" . $safeName;
					
			if (copy ($thumbnail, $thumbnail_dest)) {
				unlink($thumbnail);	
			}
			
			if (copy ($thumbnail_lg, $thumbnail_lg_dest)) {
				unlink($thumbnail_lg);	
			}
		}
		
		// Update the thumbnails table
		try {
			$thumbnail_filename = "thumb-".$safeName;
			$thumbnail_lg_filename = "thumb-lg-".$safeName;
			
			if (file_exists($_SERVER['DOCUMENT_ROOT'] . "/thumbnails/" . $thumbnail_filename)) {
				$this->stmt = $this->dbh->prepare("SELECT document_id FROM documents WHERE document_filename=:document_filename");
				$this->stmt->bindParam(":document_filename", $safeName, PDO::PARAM_STR);
				$this->stmt->execute();
				
				$results = $this->stmt->fetchAll(PDO::FETCH_ASSOC);
			
				$docId = $results[0]['document_id'];
			
				$this->stmt = $this->dbh->prepare("INSERT INTO thumbnails (document_id, thumbnail_filename, thumbnail_lg_filename) VALUES (:document_id,:thumbnail_filename,:thumbnail_lg_filename)");
				$this->stmt->bindParam(':document_id', $docId, PDO::PARAM_INT);
				$this->stmt->bindParam(':thumbnail_filename', $thumbnail_filename, PDO::PARAM_STR);
				$this->stmt->bindParam(':thumbnail_lg_filename', $thumbnail_lg_filename, PDO::PARAM_STR);
				$this->stmt->execute();
			}
		} catch(PDOException $e){
			echo $e->getMessage();
		}

		$document = $_SERVER['DOCUMENT_ROOT'] . "/ftp-uploads/" . $safeName;
		$document_archive = $_SERVER['DOCUMENT_ROOT'] . "/document-archive/" . $safeName;
		copy ($document, $document_archive);
	
		// Move document to documents folder
		$documents = $_SERVER['DOCUMENT_ROOT'] . "/documents/" . $safeName;
		
		if (copy ($document, $documents)) {
			unlink($document);	
		}
	}
		
	// Flattens array
	private function array_flat($array) {
		foreach($array as $a) {
			if(is_array($a)) {
				$tmp = array_merge($tmp, $this->array_flat($a));
			} else {
      			$tmp[] = $a;
			}
  		}
		return $tmp;
	}
	
	// Checks file name and returns a valid file name
	private function sanitize_file_name($filename) {
    	$filename_raw = $filename;
		$special_chars = array("?", "[", "]", "/", "\\", "=", "<", ">", ":", ";", ",", "'", "\"", "&", "$", "#", "*", "(", ")", "|", "~", "`", "!", "{", "}");
		$filename = str_replace($special_chars, '', $filename);
		$filename = preg_replace('/[\s-]+/', '-', $filename);
		$filename = trim($filename, '.-_');
		
		return $filename;	
	}
}
?>
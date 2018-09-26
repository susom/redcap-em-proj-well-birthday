<?php
/*
	UserPie Version: 1.0
	http://userpie.com
	
*/
namespace Stanford\WellBirthday;

$debug_mode 		= true;
$mail_templates_dir = "./";

class userPieMail {

	//UserPie uses a text based system with hooks to replace various strs in txt email templates
	public $contents = NULL;

	//Function used for replacing hooks in our templates
	public function newTemplateMsg($template,$additionalHooks){
		global $mail_templates_dir,$debug_mode;
	
		// $this->contents = file_get_contents($mail_templates_dir.$template);
		$this->contents = $template;

		//Check to see we can access the file / it has some contents
		if(!$this->contents || empty($this->contents)){
			if($debug_mode){
				if(!$this->contents){ 
					echo lang("MAIL_TEMPLATE_DIRECTORY_ERROR",array(getenv("DOCUMENT_ROOT")));
					die(); 
				}else if(empty($this->contents)){
					echo lang("MAIL_TEMPLATE_FILE_EMPTY"); 
					die();
				}
			}
			return false;
		}else{
			//Replace default hooks
			$this->contents = replaceDefaultHook($this->contents);
			//Replace defined / custom hooks
			$this->contents = str_replace($additionalHooks["searchStrs"],$additionalHooks["subjectStrs"],$this->contents);

			//Do we need to include an email footer?
			//Try and find the #INC-FOOTER hook
			if(strpos($this->contents,"#INC-FOOTER#") !== FALSE){
				$footer = file_get_contents($mail_templates_dir."email-footer.txt");
				if($footer && !empty($footer)) $this->contents .= replaceDefaultHook($footer); 
				$this->contents = str_replace("#INC-FOOTER#","",$this->contents);
			}
			
			return true;
		}
	}
	
	public function sendMail($email,$subject,$msg = NULL)
	{
		global $websiteName, $emailAddress;
		$header =  "MIME-Version: 1.0\r\n";
		$header .= "Content-type: text/html; charset=iso-8859-1\r\n";
		$header .= "From: ". $websiteName . " <" . $emailAddress . ">\r\n";
		
		//Check to see if we sending a template email.
		$message = ($msg == NULL) ? $this->contents : $msg;
		
		// $message = wordwrap($message, 70);
		
		return mail($email,$subject,$message,$header);
	}
}

// Used by UserPie Email
function replaceDefaultHook($str) {
	global $default_hooks,$default_replace;

	return (str_replace($default_hooks,$default_replace,$str));
}

function emailReminder($fname,$uid,$hooks,$email,$email_template, $email_subject, $email_msg){
	$mail = new userPieMail();

	// Build the template - Optional, you can just use the sendMail function to message
	if(!is_null($email_template) && !$mail->newTemplateMsg($email_template,$hooks)) {
		print_r("error : building template");
	 // logIt("Error building actition-reminder email template", "ERROR");
	} else {
	 // Send the mail. Specify users email here and subject.
	 // SendMail can have a third parementer for message if you do not wish to build a template.

	
	 $email_msg = str_replace($hooks["searchStrs"],$hooks["subjectStrs"],$email_template);

	 if(!is_null($email_msg) && !$mail->sendMail($email,$email_subject,$email_msg)) {
	 	print_r("error : sending email");
	    // logIt("Error sending email: " . print_r($mail,true), "ERROR");
	 } else {
	 	print_r("Email sent to $fname ($uid) @ $email <br>");
	    // Update email_act_sent_ts
	 }
	}
}
?>
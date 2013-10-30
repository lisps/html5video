<?php
/**
 * DokuWiki Plugin html5video (Syntax Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html 
 * @author lisps
 * Parts borrowed from the videogg plugin written by Ludovic Kiefer and from the html5video plugin written by 
 * Jason van Gumster (Fweeb) <jason@monsterjavaguns.com> which is based on Christophe Benz' Dailymotion plugin, which, in turn,
 * is based on Ikuo Obataya's Youtube plugin.
 *
 * Currently only supports h264 videos
 */

// must be run within Dokuwiki
if (!defined('DOKU_INC')) die();

if (!defined('DOKU_LF')) define('DOKU_LF', "\n");
if (!defined('DOKU_TAB')) define('DOKU_TAB', "\t");
if (!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');

require_once DOKU_PLUGIN.'syntax.php';

class syntax_plugin_html5video_video extends DokuWiki_Syntax_Plugin {

    public function getType() {
        return 'substition';
    }

    public function getPType() {
        return 'block';
    }

    public function getSort() {
        return 159;
    }
 
    public function connectTo($mode) { 
		// {file}.mp4?{width}x{height}?{file}|{alternatetext}
		$this->Lexer->addSpecialPattern('\{\{[^}]*(?:(?:mp4)|(?:ogv)|(?:webm))(?:\?(?:\d{2,4}x\d{2,4})?(?:\&[^\}]{1,255}.jpg|\&[^\}]{1,255}.png|\&[^\}]{1,255}.gif)?)? ?\|?[^\}]{1,255}\}\}',$mode,'plugin_html5video_video');
    }

    public function handle($match, $state, $pos, &$handler){
        $params = substr($match, strlen('{{'), - strlen('}}')); //Strip markup
		 
		// removing optional '|' without alternate text
		if( substr($params, -1) == '|' ) {
			$params = substr($params, 0, -1);  
		} else {
			// get alternate text next to '|'
			if(strpos ($params, "|")) {  
				$alternatetext = substr($params, strpos ($params, "|")+1); 
				$params = substr($params, 0, strpos ($params, "|")); 
			} 
		}
	
        if(strpos($params, ' ') === 0) { // Space as first character
            if(substr_compare($params, ' ', -1, 1) === 0) { // Space a front and back = centered
                $params = trim($params);
                $params = 'center?' . $params;
            } 
            else { // Only space at front = right-aligned
                $params = trim($params);
                $params = 'right?' . $params;
            }
        }
        elseif(substr_compare($params, ' ', -1, 1) === 0) { // Space only as last character = left-aligned
            $params = trim($params);
            $params = 'left?' . $params;
        }
        else { // No space padding = inline
            $params = 'inline?' . $params;
        }
		
		// push alternatetext to params
		$params = $params . '?' . $alternatetext;		
		
        return array(state, explode('?', $params));
    }

    public function render($mode, &$renderer, $data) {
	  
        if($mode != 'xhtml') {
			return false;
		}
		 
        list($state, $params) = $data;
        list($video_align, $video_url, $parameters, $alternatetext) = $params;
		
		// get optional parameters
		if(strpos ($parameters, "&") === false) {
			$video_size = $parameters;  
		} else { 
			$video_size = substr($parameters, 0, strpos ($parameters, "&")); 
			$video_picture = substr($parameters, strpos ($parameters, "&")+1); 
		}
		
		// debug only
		//$renderer->doc .= "video_align:" . $video_align. "<br />" . "video_url:" . $video_url. "<br />" ."video_size:" . $video_size. "<br />" ."video_picture:" . $video_picture. "<br />"."alternatetext:" . $alternatetext. "<br />" ;
        //return false;
   
        if($video_align == "center") {
            $align = "margin: 0 auto;";
        }
        elseif($video_align == "left") {
            $align = "float: left;";
        }
        elseif($video_align == "right") {
            $align = "float: right;";
        }
        else { // Inline
            $align = "";
        }

        if(!substr_count($video_url, '/')) {
            $video_url = ml($video_url);
        }

		
        if(substr($video_url, -3) != 'mp4') {
            $renderer->doc .= "Error: The video must be in mp4 format.<br />" . $video_url;
            return false;
        }

        if(is_null($video_size) or !substr_count($video_size, 'x')) {
            $width  = 640;
            $height = 360;
        }
        else {
            $obj_dimensions = explode('x', $video_size);
            $width  = $obj_dimensions[0];
            $height = $obj_dimensions[1];
        }

        if(is_null($video_attr)) {
            $attr = "";
        }

		// initalisize video class id 
        static $counter = 1;  
		
		if($video_picture != "") {
			// use custom preview picture
			$picture = $video_picture;
		} else {
			// Use global preview picture  
			$picture = $this->getConf('GlobalVideoPreviewPicture');	
		}
		
		// use url or dokuwiki
		if($picture != "" && !preg_match('|^http(s)?://[a-z0-9-]+(.[a-z0-9-]+)*(:[0-9]+)?(/.*)?$|i', $picture)) { 
			$picture = ml($picture);		
		}
		
		// preprocess content to display on screen
		$obj = '<video id="'.hsc($this->getConf('videoPlayerIDText')).'' . $counter . '" class="video-js vjs-default-skin" width="' . $width . '" height="' . $height . '" controls preload="'.hsc($this->getConf('VideoPreload')).'" poster="'.hsc($picture).	'" data-setup=\'{"techOrder": ["'.hsc($this->getConf('preferedVideoTechnologie')).'", "'.hsc($this->getConf('fallBackVideoTechnologie')).'"]}\'>';

        $obj = $obj . '<source src="' . $video_url . '" type="'.hsc($this->getConf('html5VideoType')).'"/></video>';
		
		// preprocess content to print
		if($this->getConf('showThumbOnPrint') && $picture != "") {
			// Print Picture if specified 
			$obj .= '<div class="vjs-alternatetext"><img src="' . $picture . '" alt="' . $alternatetext . '"  width="' . $width . '" height="' . $height . '"></div>'; 
		} else if($alternatetext != "") { 
			// Print alternate text if specified
			$obj .= '<div class="vjs-alternatetext">' . $alternatetext . '</div>'; 		
		} else  if($this->getConf('showStandardTextOnPrint')) {
			// Print standard alternate text
			$obj .= '<div class="vjs-alternatetext">' . hsc($this->getConf('standardAlternateTextPrint')) . '</div>'; 		 
		}
 
		// set div algin
		if($align != "") {
            $obj = '<div style="width: ' . $width . 'px; ' . $align . '">' . $obj . '</div>';
        }
	 
		// increment video class id on current page
		$counter++;	
		
		// set render output
        $renderer->doc .= $obj;
        return true;
    }
    private function _getAlts($filename) {
        return false;
    }
}

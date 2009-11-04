<?php

require_once 'HTML/QuickForm/select.php';
require_once('modules/Libs/QuickForm/FieldTypes/autocomplete/autocomplete.php');

/**
 * HTML class for an autoselect field
 * 
 * @category    HTML
 * @package     HTML_QuickForm
 * @author      Arkadiusz Bisaga <abisaga@telaxus.com>
 */
class HTML_QuickForm_autoselect extends HTML_QuickForm_select {
	private $more_opts_callback = null;
	private $more_opts_args = null;
	private $more_opts_format = null;
	
    /**
     * Class constructor
     * 
     * @param     string    Select name attribute
     * @param     mixed     Label(s) for the select
     * @param     mixed     Data to be used to populate options
     * @param     mixed     Either a typical HTML attribute string or an associative array
     * @since     1.0
     * @access    public
     * @return    void
     */
    function HTML_QuickForm_autoselect($elementName=null, $elementLabel=null, $options=null, $more_opts_callback=null, $format=null, $attributes=null) {
        HTML_QuickForm_element::HTML_QuickForm_element($elementName, $elementLabel, $attributes);
        $this->_persistantFreeze = true;
        $this->_type = 'select';
        if (isset($options)) {
			$this->load($options);
        }
		$this->more_opts_callback = $more_opts_callback[0];
		$this->more_opts_args = $more_opts_callback[1];
		$this->more_opts_format = $format;
    } //end constructor

    public static function get_autocomplete_suggestbox($string, $callback, $args) {
		if (!is_string($string)) $string = '';
    	array_unshift($args, $string);
    	$result = call_user_func_array($callback, $args);
    	$ret = '<ul style="width:290px;">';
    	if (empty($result)) {
			$ret .= '<li><span style="text-align:center;font-weight:bold;" class="informal">'.Base_LangCommon::ts('Libs/QuickForm','No records founds').'</span></li>';
    	}
    	foreach ($result as $k=>$v) {
			$ret .= '<li><span style="display:none;">'.$k.'__'.$v.'</span><span class="informal">'.$v.'</span></li>';
    	}
    	$ret .= '</ul>';
    	return $ret;
    }

    function toHtml()
    {
        if ($this->_flagFrozen) {
            return $this->getFrozenHtml();
        } else {
            $tabs    = $this->_getTabs();
            $strHtml = '';

            if ($this->getComment() != '') {
                $strHtml .= $tabs . '<!-- ' . $this->getComment() . " //-->\n";
            }

            $myName = $this->getName();
			$this->updateAttributes(array('id'=>$myName));
			eval_js('Event.observe("'.$myName.'", "keydown", function(ev){autoselect_start_searching("'.$myName.'", ev.keyCode)});');
            if (!$this->getMultiple()) {
                $attrString = $this->_getAttrString($this->_attributes);
            } else {
                $this->setName($myName . '[]');
                $attrString = $this->_getAttrString($this->_attributes);
                $this->setName($myName);
            }
            $strHtml .= $tabs . '<select' . $attrString . ">\n";

			$val = $this->getValue();
			if (isset($val[0]))
				$this->addOption(strip_tags(call_user_func_array($this->more_opts_format, array($val[0]))), $val[0]);
			// TODO: minor bug - will duplicate entry if already present, plus need to strip of tags
				
            $strValues = is_array($this->_values)? array_map('strval', $this->_values): array();
            foreach ($this->_options as $option) {
                if (!empty($strValues) && in_array($option['attr']['value'], $strValues, true)) {
                    $option['attr']['selected'] = 'selected';
                }
                $strHtml .= $tabs . "\t<option" . $this->_getAttrString($option['attr']) . '>' .
                            $option['text'] . "</option>\n";
            }
			$strHtml .= $tabs . '</select>';

			$search = new HTML_QuickForm_autocomplete($myName.'__search','', array('HTML_QuickForm_autoselect','get_autocomplete_suggestbox'), array($this->more_opts_callback, $this->more_opts_args));
			load_js('modules/Libs/QuickForm/FieldTypes/autoselect/autoselect.js');
			$search->on_hide_js('autoselect_on_hide("'.$myName.'");');

            return 	'<span id="__'.$myName.'_select_span">'.
						$strHtml.
					'</span>'.
					'<span id="__'.$myName.'_autocomplete_span" style="display:none;">'.
						$search->toHtml().
					'</span>';
        }
    } //end func toHtml

    function exportValue(&$submitValues, $assoc = false) {
        $value = $this->_findValue($submitValues);
        if (is_null($value)) {
            $value = $this->getValue();
        } elseif(!is_array($value)) {
            $value = array($value);
        }
		$cleanValue = $value;
        if (is_array($cleanValue) && !$this->getMultiple()) {
            return $this->_prepareValue($cleanValue[0], $assoc);
        } else {
            return $this->_prepareValue($cleanValue, $assoc);
        }
	}
}




/*
    private $callback;
    private $args = array();
    private $on_hide_js_code = '';
            
    function HTML_QuickForm_autocomplete($elementName=null, $elementLabel=null, $callback=null, $args=null, $attributes=null) {
        HTML_QuickForm_input::HTML_QuickForm_input($elementName, $elementLabel, $attributes);
        $this->callback = $callback;
        if (!$args || !is_array($args)) $args = array();
        $this->args = $args;
        $this->_persistantFreeze = true;
        $this->setType('text');
    }
    
    function on_hide_js($js) {
    	$this->on_hide_js_code = $js;
    }

    function toHtml() {
        if ($this->_flagFrozen) {
            return $this->getFrozenHtml();
        } else {
        	$name = $this->getAttribute('name');
        	$id = $this->getAttribute('id');
        	if (!$id) {
        		$id = '__autocomplete_id_'.$name;
        		$this->setAttribute('id', $id);
        	}
			print('<div id="'.$id.'_suggestbox" class="autocomplete">&nbsp;</div>');
			$key = md5(serialize($this->callback).$id);
			$_SESSION['client']['quickform']['autocomplete'][$key] = array('callback'=>$this->callback, 'field'=>$name, 'args'=>$this->args);
			eval_js('var epesi_autocompleter = new Ajax.Autocompleter(\''.$id.'\', \''.$id.'_suggestbox\', \'modules/Libs/QuickForm/FieldTypes/autocomplete/autocomplete_update.php?'.http_build_query(array('cid'=>CID, 'key'=>$key)).'\', \'\');');

			// TODO: not really neat, need to extend the function automatically
			if ($this->on_hide_js_code) eval_js('epesi_autocompleter.hide=function(){'.
					'this.stopIndicator();'.
				    'if (Element.getStyle(this.update, "display") != "none") {'.
				    '    this.options.onHide(this.element, this.update);'.
				    '}'.
				    'if (this.iefix) {'.
				    '    Element.hide(this.iefix);'.
				    '}'.
					$this->on_hide_js_code.
				'}');

            return $this->_getTabs() . '<input' . $this->_getAttrString($this->_attributes) . ' />';
        }
    } //end func toHtml
	        
}
?>
*/
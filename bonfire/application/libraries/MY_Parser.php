<?php defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * MY Parser 
 *
 * @package 	Bonfire	
 * @subpackage	Libraries
 * @category 	Parser
 * @author 	Avinash Kundaliya
 * @link	http://cibonfire.com		
 */

class MY_Parser extends CI_Parser 
{
	protected $_module;

	function __construct()
	{
		$this->_module = CI::$APP->router->fetch_module();
		if( ! class_exists('Lex_Parser'))
		{
			include APPPATH.'/libraries/Lex/Parser.php';
		}
	}

	public function parse($template, $data, $return = FALSE)
	{
		$CI =& get_instance();

		// Convert from object to array
		is_array($data) or $data = (array) $data;
		
		//somehow the template doesnt pass the data .. its in _ci_cached_vars .. w
		$data = array_merge($data, $CI->load->_ci_cached_vars);

		$parsedString = $CI->load->view($template, $data, TRUE);

		$parser = new Lex_Parser();
		$parser->scopeGlue(':');

		$parsed = $parser->parse($parsedString, $data, array($this, 'parser_callback'));

		if ( ! $return)
		{
			$CI->output->append_output($parsed);
			return;
		}

		return $parsed;
		
	}

	public function parser_callback($module, $attribute, $content)
	{

		$CI =& get_instance();
		
		$return_view = NULL;
		$parsed_return = '';


		// Get the required module

		$module = str_replace(':','/',$module);

		$method = 'index';
		
		if(($pos = strrpos($module, '/')) != FALSE) {
			$method = substr($module, $pos + 1);		
			$module = substr($module, 0, $pos);
		}

		//load the module
		if($class = $CI->load->module($module))
		{

			//if the method is callable
			if (method_exists($class, $method))	{

				ob_start();
				$output = call_user_func_array(array($class, $method), $attribute);
				$buffer = ob_get_clean();
				$output = ($output !== NULL) ? $output : $buffer;

				$return_view = $output;

				//loop it up
				if(is_array($output))
				{

					$parser = new Lex_Parser();
					$parser->scopeGlue(':');
					
					foreach($output as $result)
					{
						$parsed_return .= $parser->parse($content, $result, array($this, 'parser_callback'));
					}
					
					unset($parser);

					$return_view =  $parsed_return;
				}
			}
		}

		return $return_view;
	}
}

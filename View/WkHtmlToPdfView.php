<?php

App::uses('View', 'View');
App::uses('CakeRequest', 'Network');
App::uses('String', 'Utility');
App::uses('Folder', 'Utility');
App::uses('File', 'Utility');

class WkHtmlToPdfView extends View {

	protected $sourceFile = null;

	/**
	 * @brief the default options for WkHtmlToPdf View class
	 * 
	 * @access protected
	 * @var array
	 */
	protected $options = array(
		'footer' => array(),
		'header' => array(),
		'orientation' => 'Portrait',
		'pageSize' => 'A4',
		'mode' => 'download',
		'filename' => 'output.pdf',
		'binary' => '/usr/bin/wkhtmltopdf',
		'copies' => 1,
		'toc' => false,
		'grayscale' => false,
		'username' => false,
		'password' => false
	);

	/**
	 * @brief public interface for rendering pdfs
	 * 
	 * This is the render method that will be called by cake as per normal 
	 * view classes.
	 * 
	 * Depending on the options that are configured, WkHtmlToPdf will either
	 * offer the pdf for download, embed it directly in the browser, save the 
	 * data to disk or return the raw pdf data to the calling method.
	 * 
	 * @access public
	 * 
	 * @param string $action the action being rendered
	 * @param string $layout the layout being used
	 * @param string $file a specifc file to render
	 * 
	 * @return mixed 
	 */
	public function render($action = null, $layout = null, $file = null) {
		parent::render($action, $layout, $file);

		$this->_prepare();

		if(empty($this->options['title'])) {
			$this->options['title'] = $this->fetch('title');
		}

		if(!is_executable($this->options['binary'])) {
			throw new Exception($this->options['binary'] . ' is not executable.');
		}

		if(!function_exists('proc_open')) {
			throw new Exception('Settings on the server prevent shell commands from being executed.');
		}

		$filename = $this->options['filename'];

		$rawData = $this->_renderPdf();

		switch($this->options['mode']) {
			case 'download':
				$this->response->type('application/pdf');

				$this->response->header(array(
					'Content-Description' => 'File Transfer',
					'Cache-Control' => 'public; must-revalidate, max-age=0',
					'Pragma' => 'public',
					'Content-Transfer-Encoding' => 'binary',
				));

				$this->response->expires();
				$this->response->modified();
				$this->response->length(mb_strlen($rawData));

				$this->response->download(pathinfo($filename, PATHINFO_BASENAME));

				break;
			case 'embedded':
				$this->response->type('application/pdf');

				$this->response->header(array(
					'Cache-Control' => 'public; must-revalidate, max-age=0',
					'Pragma' => 'public',
					'Content-Disposition' => 'inline; filename="' . pathinfo($filename, PATHINFO_BASENAME) . '";'
					
				));

				$this->response->expires();
				$this->response->modified();
				$this->response->length(mb_strlen($rawData));
	
				break;

			case 'string':
				return $this->output = $rawData;
				break;

			case 'save':
				file_put_contents($filename, $rawData);
				break;
			
			default:
				throw new Exception("Mode: " . $mode . " is not supported");
		}

		$this->response->send();

		return $rawData;
	}

	/**
	 * @breif execute the WkHtmlToPdf commands for rendering pdfs
	 * 
	 * @access private
	 * 
	 * @param string $cmd the command to execute
	 * @param string $input
	 * 
	 * @return string the result of running the command to generate the pdf 
	 */
	private function __exec($cmd, $input = '') {
		$result = array('stdout' => '', 'stderr' => '', 'return' => '');

		$proc = proc_open($cmd, array(0 => array('pipe', 'r'), 1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes);
		fwrite($pipes[0], $input);
		fclose($pipes[0]);

		$result['stdout'] = stream_get_contents($pipes[1]);
		fclose($pipes[1]);

		$result['stderr'] = stream_get_contents($pipes[2]);
		fclose($pipes[2]);

		$result['return'] = proc_close($proc);

		return $result;
	}

	/**
	 * @brief build up parts of the command that will later be executed
	 * 
	 * @access private
	 * 
	 * @param string $commandType the part of the command to build up
	 * 
	 * @return string a part of the command for rendering pdfs 
	 */
	private function __subCommand($commandType) {
		$data = $this->options[$commandType];
		$command = '';

		if(count($data) > 0) {
			$availableCommands = array(
				'left', 'right', 'center', 'font-name', 'html', 'line', 'spacing', 'font-size'
			);

			foreach($data as $key => $value) {
				if(in_array($key, $availableCommands)) {
					$command .= " --$commandType-$key \"$value\"";
				}
			}
		}

		return $command;
	}

	/**
	 * @brief get the command to render a pdf 
	 * 
	 * @access private
	 * 
	 * @return string the command for generating the pdf
	 */
	private function __getCommand() {
		$command = $this->options['binary'];

		$command .= ($this->options['copies'] > 1) ? " --copies " . $this->options['copies'] : "";
		$command .= " --orientation " . $this->options['orientation'];
		$command .= " --page-size " . $this->options['pageSize'];
		$command .= ($this->options['toc'] === true) ? " --toc" : "";
		$command .= ($this->options['grayscale'] === true) ? " --grayscale" : "";
		$command .= ($this->options['password'] !== false) ? " --password " . $this->options['password'] : "";
		$command .= ($this->options['username'] !== false) ? " --username " . $this->options['username'] : "";
		$command .= $this->__subCommand('footer') . $this->__subCommand('header');

		$command .= ' --title "' . $this->options['title'] . '"';
		$command .= ' "%input%"';
		$command .= " -";
		
		return $command;
	}

	/**
	 * @brief render a pdf document from some html
	 * 
	 * @access protected
	 * 
	 * @return the data from the rendering
	 */
	private function _renderPdf() {
		$content = $this->__exec(str_replace('%input%', $this->sourceFile->pwd(), $this->__getCommand()));

		if(strpos(mb_strtolower($content['stderr']), 'error')) {
			throw new Exception("System error <pre>" . $content['stderr'] . "</pre>");
		}

		if(mb_strlen($content['stdout'], 'utf-8') === 0) {
			throw new Exception("WKHTMLTOPDF didn't return any data");
		}

		if((int)$content['return'] > 1) {
			throw new Exception("Shell error, return code: " . (int)$content['return']);
		}

		return $content['stdout'];
	}

	/**
	 * @brief Prepares the temporary file paths and source file with the html data
	 * 
	 * @access protected
	 * 
	 * @return void
	 */
	protected function _prepare() {
		$path = TMP . 'wk_html_to_pdf' . DS;

		//Make sure the folder exists
		new Folder($path, true);

		$this->sourceFile = new File($path . String::uuid() . '.html', true);
		$this->sourceFile->write($this->output);
		$this->sourceFile->close();

		return;
	}

	/**
	 * @brief public interface for setting options
	 * 
	 * This is a basic setter method for setting options from external sources.
	 * 
	 * You can pass one option like this:
	 * 
	 * setOption('author', 'John Smith');
	 * 
	 * Or multiple options like this:
	 * 
	 * setOption(array('author' => 'John Smith', 'pageSize' => 'A4'));
	 * 
	 * @access public
	 * 
	 * @param mixed $key Key to set
	 * @param mixed $value Value to set (optional)
	 * 
	 * @return void
	 */
	public function setOption($key, $value = null) {
		if(is_array($key)) {
			foreach($key as $keyName => $keyValue) {
				$this->setOption($keyName, $keyValue);
			}

			return;
		}

		$this->options[$key] = $value;
	}	
}

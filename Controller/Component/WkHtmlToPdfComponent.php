<?php


App::uses('Component', 'Controller');

class WkHtmlToPdfComponent extends Component {

	/**
	 * @brief initialise the WkHtmlToPdf Component
	 * 
	 * If there is an extention "pdf" in the url automatically set WkHtmlToPdf 
	 * as the View class
	 * 
	 * @access public
	 * 
	 * @param type $controller 
	 * 
	 * @return void
	 */
	public function startup(Controller $controller) {
		if(isset($controller->request->params['ext']) && $controller->request->params['ext'] == 'pdf') {
			$controller->viewClass = 'WkHtmlToPdf.WkHtmlToPdf';
			$controller->layout = 'WkHtmlToPdf.pdf/default';
		}
	}
}

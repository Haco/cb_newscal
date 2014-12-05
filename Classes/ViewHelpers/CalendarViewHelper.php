<?php

namespace Cbrunet\CbNewscal\ViewHelpers;

class CalendarViewHelper extends \TYPO3\CMS\Fluid\Core\ViewHelper\AbstractViewHelper {

	/**
	 * @param array $newsList 
	 * @param int $month 
	 * @param int $year 
	 * @return string Rendered result
	 */
	public function render($newsList, $month=NULL, $year=NULL) {
		if ($year === NULL) {
			$year = date('Y');
		}
		$year = (int)$year;

		if ($month === NULL) {
			$month = date('n');
		}
		$month = (int)$month;

		$fdom = mktime(0, 0, 0, $month, 1, $year);
		$fdow = (int)date('w', $fdom);

		$ldom = mktime(0, 0, 0, $month, (int)date('t'), $year);
		$ldow = (int)date('w', $ldom);

		$now = (int)date('W', $ldow) - (int)date('W', $fdow) + 1;
		$fd = 1 - $fdow;
		$ld = (int)date('t') + 6 - $ldow;
		
		$weeks = [];
		while ($fd < $ld) {
			$week = array();
			for ($d=0; $d<7; $d++) {
				$day = array();
				$dts = mktime(0, 0, 0, $month, $fd, $year);
				$day['ts'] = $dts;
				$day['day'] = (int)date('j', $dts);
				$day['month'] = (int)date('n', $dts);
				$day['curmonth'] = $day['month'] == $month;
				$day['news'] = [];
				foreach ($newsList as $key=>$news) {
					if ($news->getDatetime()->format('Y-m-d') == date('Y-m-d', $dts)) {
						$day['news'][] = $news;
						unset($newsList[$key]);
					}
				}
				$fd++;
				$week[] = $day;
			}
			$weeks[] = $week;
		}

		$this->templateVariableContainer->add('weeks', $weeks);
		$output = $this->renderChildren();
		$this->templateVariableContainer->remove('weeks');

		return $output;
	}

}

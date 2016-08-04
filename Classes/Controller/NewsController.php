<?php

namespace Cbrunet\CbNewscal\Controller;

    /* Initialization */

/*
	Version Number
*/
define('ADODB_DATE_VERSION',0.35);

$ADODB_DATETIME_CLASS = (PHP_VERSION >= 5.2);

/*
	This code was originally for windows. But apparently this problem happens
	also with Linux, RH 7.3 and later!

	glibc-2.2.5-34 and greater has been changed to return -1 for dates <
	1970.  This used to work.  The problem exists with RedHat 7.3 and 8.0
	echo (mktime(0, 0, 0, 1, 1, 1960));  // prints -1

	References:
	 http://bugs.php.net/bug.php?id=20048&edit=2
	 http://lists.debian.org/debian-glibc/2002/debian-glibc-200205/msg00010.html
*/

if (!defined('ADODB_ALLOW_NEGATIVE_TS')) define('ADODB_NO_NEGATIVE_TS',1);

class NewsController extends \GeorgRinger\News\Controller\NewsController {

	protected $year;
	protected $month;

	protected $events;

	/**
	 *
	 *
	 * @param array $overwriteDemand
	 * @return void
	 */
	public function calendarAction(array $overwriteDemand = NULL) {
		$months = array();
		$demand = $this->createDemandObject($overwriteDemand);
		$monthsBefore = (int)$this->settings['monthsBefore'];
		$monthsAfter = (int)$this->settings['monthsAfter'];

		for ($m = $this->month - $monthsBefore; $m <= $this->month + $monthsAfter; $m++) {
			$cm = $this->adodb_mktime(0, 0, 0, $m, 1, $this->year);
			$month = date('n', $cm);
			$year = date('Y', $cm);
			$months[] = array(
				'month' => $month,
				'year' => $year,
				'curmonth' => ($month == $this->month),
				'weeks' => $this->getWeeks($demand, $month, $year)
			);
		}

		$this->view->assignMultiple(array(
			'months' => $months,
			'navigation' => $this->createNavigationArray()
		));
	}

	protected function createDemandObject($overwriteDemand) {
		$this->year = (int)date('Y');
		$this->month = (int)date('n');


        if (\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::isLoaded('eventnews')) {
			$demand = $this->createDemandObjectFromSettings($this->settings, 'GeorgRinger\\Eventnews\\Domain\\Model\\Dto\\Demand');
		}
		else {
			$demand = $this->createDemandObjectFromSettings($this->settings);
		}
		if ($overwriteDemand !== NULL) {
			$demand = $this->overwriteDemandObject($demand, $overwriteDemand);
		}

		if ($demand->getYear() !== NULL) {
			$this->year = $demand->getYear();
			$demand->setYear(NULL);
		}
		if ($demand->getMonth() !== NULL) {
			$this->month = $demand->getMonth();
			$demand->setMonth(NULL);
		}

		$demand->setOrder($demand->getDateField() . ' asc');

		if ($overwriteDemand === NULL) {
			// Use settings.displayMonth only if no demand object
			$displayMonth = $this->settings['displayMonth'];

			// Display relative to current month
			if (strlen($displayMonth) > 1 && ($displayMonth[0] == '-' || $displayMonth[0] == '+')) {
				$displayMonth = (int)$displayMonth;
				$mt = $this->adodb_mktime(0, 0, 0, $this->month + $displayMonth, 1, $this->year);
				$this->year = (int)date('Y', $mt);
				$this->month = (int)date('n', $mt);
			}

			// Display absolute month
			if (strlen($displayMonth) == 7 && $displayMonth[4] == '-') {
				$this->year = (int)substr($displayMonth, 0, 4);
				$this->month = (int)substr($displayMonth, 5, 2);
			}
		}

		return $demand;
	}

	protected function getEventsOfDay($demand, &$day)
	{
		if (!isset($this->events[$day['year']]))
			$this->events[$day['year']] = array();
		if (!isset($this->events[$day['year']][$day['month']]))
		{
			$demand->setYear($day['year']);
			$demand->setMonth($day['month']);
			$this->events[$day['year']][$day['month']] = $this->newsRepository->findDemanded($demand);
		}

		$day['startev'] = True;
		$day['endev'] = True;
		$day['news'] = array();
		foreach ($this->events[$day['year']][$day['month']] as $k => $event)
		{
			switch ($demand->getDateField())
			{
				case 'datetime':
        			if (\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::isLoaded('eventnews'))
        			{
        				if ($event->getEventEnd())
        				{
        					if ($event->getDatetime()->format('Y-m-d') <= $day['cd'] &&
        						$event->getEventEnd()->format('Y-m-d') >= $day['cd'] )
        					{
        						$day['news'][] = $event;
        						if ($event->getDatetime()->format('Y-m-d') < $day['cd'])
        						{
        							$day['startev'] = False;
        						}
        						if ($event->getEventEnd()->format('Y-m-d') > $day['cd'])
        						{
        							$day['endev'] = False;
        						}
        						else
        						{
        						}
        					}
        					continue 2;  // next loop iteration
        				}
        			}


					if ($event->getDatetime()->format('Y-m-d') == $day['cd'])
					{
						$day['news'][] = $event;
					}
					break;

				case 'archive':
					if ($event->getArchive()->format('Y-m-d') == $day['cd'])
					{
						$day['news'][] = $event;
					}
					break;

				case 'crdate':
					if ($event->getCrdate()->format('Y-m-d') == $day['cd'])
					{
						$day['news'][] = $event;
					}
					break;

				case 'tstamp':
					if ($event->getTstamp()->format('Y-m-d') == $day['cd'])
					{
						$day['news'][] = $event;
					}
					break;

                case 'ecom_event_date':
                    if ($event->getEcomEventDate()) {
                        if ($event->getEcomEventDate()->format('Y-m-d') == $day['cd']) {
                            $day['news'][] = $event;
                        }
                   }
                   break;
			}
		}

	}


	protected function getWeeks($demand, $month, $year) {
		$curday = $this->firstDayOfMonth($month, $year);
		$lastday = date('t', $this->adodb_mktime(0, 0, 0, $month, 1, $year));
		$weeks = array();
		while ($curday <= $lastday) {
			$week = array();
			for ($d=0; $d<7; $d++) {
                $dts = $this->adodb_mktime(0, 0, 0, $month, $curday, $year);
				$day = array();
				$day['ts'] = $dts;
				$day['day'] = date('j', $dts);
				$day['month'] = date('n', $dts);
				$day['year'] = date('Y', $dts);
				$day['cd'] = date('Y-m-d', $dts);
				$day['curmonth'] = $day['month'] == $month;
				$day['curday'] = date('Ymd') == date('Ymd', $day['ts']);

				$this->getEventsOfDay($demand, $day);

				$week[] = $day;
				$curday++;
			}
			$weeks[] = $week;
		}

		$demand->setYear(NULL);
		$demand->setMonth(NULL);
		$demand->setDay(NULL);
		return $weeks;
	}

	/**
	 * Return the first day of the month as (nagative) offset from day 1
	 *
	 * @return int
	 **/
	protected function firstDayOfMonth($month, $year) {
		$fdom = $this->adodb_mktime(0, 0, 0, $month, 1, $year);  // First day of the month
		$fdow = (int)date('w', $fdom);  // Day of week of the first day

		$fd = 1 - $fdow + $this->settings['firstDayOfWeek'];  // First day of the calendar
		if ($fd > 1) {
			$fd -= 7;
		}
		return $fd;
	}


	/**
	 * Create the array needed for navigation.
	 *
	 * Returned array contains:
	 *    uid:            uid of current content object
	 *    numberOfMonths: number of displayed months
	 *    prev:           month and year for previous arrow
	 *    next:           month and year for mext arrow
	 *
	 * @return array
	 **/
	protected function createNavigationArray() {
		$navigation = array('prev' => array(), 'next' => array());
		$monthsBefore = (int)$this->settings['monthsBefore'];
		$monthsAfter = (int)$this->settings['monthsAfter'];
		$navigation['numberOfMonths'] = $monthsBefore + 1 + $monthsAfter;

		switch ((int)$this->settings['scrollMode']) {
			case -1:
				$monthsToScroll = $monthsBefore + $monthsAfter > 0 ? $monthsBefore + $monthsAfter : 1;
				break;
			case 0:
				$monthsToScroll = $monthsBefore + 1 + $monthsAfter;
				break;
			default:
					$monthsToScroll = (int)$this->settings['scrollMode'];
				break;
		}

		$prevdate = $this->adodb_mktime(0, 0, 0, $this->month - $monthsToScroll, 1, $this->year);
		if ($this->settings['timeRestriction']) {
			$ts = strtotime($this->settings['timeRestriction']);
			$trm = $this->adodb_mktime(0, 0, 0, date('n', $ts), 1, date('Y', $ts));
			if ($prevdate < $trm) {
				$navigation['prev'] = NULL;
			}
		}
		if (is_array($navigation['prev'])) {
			$navigation['prev']['month'] = date('m', $prevdate);
			$navigation['prev']['year'] = date('Y', $prevdate);
		}

		$nextdate = $this->adodb_mktime(0, 0, 0, $this->month + $monthsToScroll, 1, $this->year);
		if ($this->settings['timeRestrictionHigh']) {
			$ts = strtotime($this->settings['timeRestrictionHigh']);
			$trm = $this->adodb_mktime(0, 0, 0, date('n', $ts), 1, date('Y', $ts));
			if ($nextdate > $trm) {
				$navigation['next'] = NULL;
			}
		}
		if (is_array($navigation['next'])) {
			$navigation['next']['month'] = date('m', $nextdate);
			$navigation['next']['year'] = date('Y', $nextdate);
		}

		$this->contentObj = $this->configurationManager->getContentObject();
		$navigation['uid'] = $this->contentObj->data['uid'];

		return $navigation;
	}

    public function adodb_mktime($hr,$min,$sec,$mon=false,$day=false,$year=false,$is_dst=false,$is_gmt=false)
    {
        if (!defined('ADODB_TEST_DATES')) {

            if ($mon === false) {
                return $is_gmt? @gmmktime($hr,$min,$sec): @mktime($hr,$min,$sec);
            }

            // for windows, we don't check 1970 because with timezone differences,
            // 1 Jan 1970 could generate negative timestamp, which is illegal
            $usephpfns = (1970 < $year && $year < 2038
                || !defined('ADODB_NO_NEGATIVE_TS') && (1901 < $year && $year < 2038)
            );


            if ($usephpfns && ($year + $mon/12+$day/365.25+$hr/(24*365.25) >= 2038)) $usephpfns = false;

            if ($usephpfns) {
                return $is_gmt ?
                    @gmmktime($hr,$min,$sec,$mon,$day,$year):
                    @mktime($hr,$min,$sec,$mon,$day,$year);
            }
        }

        $gmt_different = ($is_gmt) ? 0 : $this->adodb_get_gmt_diff($year,$mon,$day);

        /*
        # disabled because some people place large values in $sec.
        # however we need it for $mon because we use an array...
        $hr = intval($hr);
        $min = intval($min);
        $sec = intval($sec);
        */
        $mon = intval($mon);
        $day = intval($day);
        $year = intval($year);


        $year = $this->adodb_year_digit_check($year);

        if ($mon > 12) {
            $y = floor(($mon-1)/ 12);
            $year += $y;
            $mon -= $y*12;
        } else if ($mon < 1) {
            $y = ceil((1-$mon) / 12);
            $year -= $y;
            $mon += $y*12;
        }

        $_day_power = 86400;
        $_hour_power = 3600;
        $_min_power = 60;

        $_month_table_normal = array("",31,28,31,30,31,30,31,31,30,31,30,31);
        $_month_table_leaf = array("",31,29,31,30,31,30,31,31,30,31,30,31);

        $_total_date = 0;
        if ($year >= 1970) {
            for ($a = 1970 ; $a <= $year; $a++) {
                $leaf = $this->_adodb_is_leap_year($a);
                if ($leaf == true) {
                    $loop_table = $_month_table_leaf;
                    $_add_date = 366;
                } else {
                    $loop_table = $_month_table_normal;
                    $_add_date = 365;
                }
                if ($a < $year) {
                    $_total_date += $_add_date;
                } else {
                    for($b=1;$b<$mon;$b++) {
                        $_total_date += $loop_table[$b];
                    }
                }
            }
            $_total_date +=$day-1;
            $ret = $_total_date * $_day_power + $hr * $_hour_power + $min * $_min_power + $sec + $gmt_different;

        } else {
            for ($a = 1969 ; $a >= $year; $a--) {
                $leaf = $this->_adodb_is_leap_year($a);
                if ($leaf == true) {
                    $loop_table = $_month_table_leaf;
                    $_add_date = 366;
                } else {
                    $loop_table = $_month_table_normal;
                    $_add_date = 365;
                }
                if ($a > $year) { $_total_date += $_add_date;
                } else {
                    for($b=12;$b>$mon;$b--) {
                        $_total_date += $loop_table[$b];
                    }
                }
            }
            $_total_date += $loop_table[$mon] - $day;

            $_day_time = $hr * $_hour_power + $min * $_min_power + $sec;
            $_day_time = $_day_power - $_day_time;
            $ret = -( $_total_date * $_day_power + $_day_time - $gmt_different);
            if ($ret < -12220185600) $ret += 10*86400; // if earlier than 5 Oct 1582 - gregorian correction
            else if ($ret < -12219321600) $ret = -12219321600; // if in limbo, reset to 15 Oct 1582.
        }
        //print " dmy=$day/$mon/$year $hr:$min:$sec => " .$ret;
        return $ret;
    }

    /**
    Fix 2-digit years. Works for any century.
    Assumes that if 2-digit is more than 30 years in future, then previous century.
     */
    public function adodb_year_digit_check($y)
    {
        if ($y < 100) {

            $yr = (integer) date("Y");
            $century = (integer) ($yr /100);

            if ($yr%100 > 50) {
                $c1 = $century + 1;
                $c0 = $century;
            } else {
                $c1 = $century;
                $c0 = $century - 1;
            }
            $c1 *= 100;
            // if 2-digit year is less than 30 years in future, set it to this century
            // otherwise if more than 30 years in future, then we set 2-digit year to the prev century.
            if (($y + $c1) < $yr+30) $y = $y + $c1;
            else $y = $y + $c0*100;
        }
        return $y;
    }

    /**
    Checks for leap year, returns true if it is. No 2-digit year check. Also
    handles julian calendar correctly.
     */
    public function _adodb_is_leap_year($year)
    {
        if ($year % 4 != 0) return false;

        if ($year % 400 == 0) {
            return true;
            // if gregorian calendar (>1582), century not-divisible by 400 is not leap
        } else if ($year > 1582 && $year % 100 == 0 ) {
            return false;
        }

        return true;
    }

    /**
    get local time zone offset from GMT. Does not handle historical timezones before 1970.
     */
    public function adodb_get_gmt_diff($y,$m,$d)
    {
        static $TZ,$tzo;
        global $ADODB_DATETIME_CLASS;

        if (!defined('ADODB_TEST_DATES')) $y = false;
        else if ($y < 1970 || $y >= 2038) $y = false;

        if ($ADODB_DATETIME_CLASS && $y !== false) {
            $dt = new \DateTime();
            $dt->setISODate($y,$m,$d);
            if (empty($tzo)) {
                $tzo = new \DateTimeZone(date_default_timezone_get());
                #	$tzt = timezone_transitions_get( $tzo );
            }
            return -$tzo->getOffset($dt);
        } else {
            if (isset($TZ)) return $TZ;
            $y = date('Y');
            /*
            if (function_exists('date_default_timezone_get') && function_exists('timezone_offset_get')) {
                $tzonename = date_default_timezone_get();
                if ($tzonename) {
                    $tobj = new DateTimeZone($tzonename);
                    $TZ = -timezone_offset_get($tobj,new DateTime("now",$tzo));
                }
            }
            */
            if (empty($TZ)) $TZ = mktime(0,0,0,12,2,$y) - gmmktime(0,0,0,12,2,$y);
        }
        return $TZ;
    }

}

<?php

namespace WooWMS\Enums;

/**
 * Available types of admin notices:
 *
 * ERROR - error type notice
 *
 * WARNING - warning type notice
 *
 * INFO - info type notice
 *
 * SUCCESS - success type notice
 */
enum AdminNoticeType: string {
	case ERROR = 'error';
	case WARNING = 'warning';
	case INFO = 'info';
	case SUCCESS = 'success';
}
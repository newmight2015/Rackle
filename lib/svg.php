<?php
	class SVG {
		public static function percentageCircle($val, $maxval, $radius) {
			$angle = 360 / $maxval * $val; // Calculate the angle in degrees
			$radian = ($angle * M_PI / 180); // Convert angle to radians
			$x = sin($radian) * $radius;
			$y = cos($radian) * -$radius;
			$super180 = ($angle > 180 ? 1 : 0);
			return "M 0 0 v -$radius A $radius $radius 1 $super180 1 $x $y z";
		}
	}
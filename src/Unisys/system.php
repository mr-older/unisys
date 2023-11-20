<?php

namespace UniSys;


class System
{
	public $start_resources, $last_resources, $unit, $error = "";
	private $units_conversion = [	'tb' => 1000000000000,
									'gb' => 1000000000,
									'mb' => 1000000,
									'kb' => 1000,
									'b'  => 1
								];
#    struct timeval ru_utime; /* user time used */
#    struct timeval ru_stime; /* system time used */
#    long   ru_maxrss;        /* maximum resident set size */
#    long   ru_minflt;        /* page reclaims */
#    long   ru_majflt;        /* page faults */
#    long   ru_nswap;         /* swaps */
#    long   ru_inblock;       /* block input operations */
#    long   ru_oublock;       /* block output operations */
#    long   ru_msgsnd;        /* messages sent */
#    long   ru_msgrcv;        /* messages received */
#    long   ru_nsignals;      /* signals received */
#    long   ru_nvcsw;         /* voluntary context switches */
#    long   ru_nivcsw;        /* involuntary context switches */

	private function unitConvert($variable, $unit_from)
	{
#		if(	empty($unit_from = empty($this->units_conversion[$unit_from]) ? "" : $this->units_conversion[$unit_from]) ||
		if(	empty($unit_from = $this->units_conversion[$unit_from] ?? "") ||
			empty($unit_to = $this->units_conversion[$this->unit] ?? "")
		) {
			return false;
		}

		return $variable * $unit_from / $unit_to;
	}

	public function __construct($unit = "mb") {
		$this->unit = strtolower($unit);
		$this->start_resources = getrusage();
		$this->start_resources["memory_usage"] = $this->unitConvert(memory_get_usage(true), 'b');
		$this->start_resources["resident_memory"] = $this->unitConvert($this->start_resources['ru_maxrss'], 'kb');
		$this->start_resources["peak_memory_usage"] = $this->unitConvert(memory_get_peak_usage(true), 'b');
	}

	public function getResorces()	{
		$this->last_resources = getrusage();
		$this->last_resources["memory_usage"] = $this->unitConvert(memory_get_usage(true), 'b');
		$this->last_resources["resident_memory"] = $this->unitConvert($this->start_resources['ru_maxrss'], 'kb');
		$this->last_resources["peak_memory_usage"] = $this->unitConvert(memory_get_peak_usage(true), 'b');
		return $this->last_resources;
	}

	public function unlockFile($path) {
		@flock($this->lockfile, LOCK_UN);
		@fclose($this->lockfile);
		@unlink($path);
	}

	public function lockFile($path) {
		if(@file_exists($path)) {
			$this->lockfile = @fopen($path, "r");
			if(!flock($this->lockfile, LOCK_EX | LOCK_NB))
			{
				$this->error = "couldn`t lock $path";
				fclose($this->lockfile);
				return false;
			}
			fclose($this->lockfile);
		}

		$this->lockfile = @fopen($path, "w+");

		if(	!@flock($this->lockfile, LOCK_EX | LOCK_NB) ||
			!@fwrite($this->lockfile, getmypid()) ||
			!@fflush($this->lockfile))
		{
			$this->error = "couldn`t write pid to $path";
			return false;
		}

		if(@file_get_contents($path) != getmypid()) {
			$this->error = "pid in $path doesn`t match";
			return false;
		}

		register_shutdown_function([$this, 'unlockFile'], $path);

		return true;
	}

	public function ifPortOpen($address, $port) {
		$con = @fsockopen($address, $port);
		if(!is_resource($con)) return false;
		fclose($con);
		return true;
	}

	public function isXterm() {
		exec('echo $TERM', $output, $return_code);
		foreach((array) $output as $val) {
			if($val == 'xterm') return true;
		}
		return false;
	}

}

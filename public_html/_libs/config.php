<?php
// Backwards-compatible shim. The loader lives in SJ\Core\Config (src/Core/Config.php);
// sj_config() still receives the same array. See SECURITY.md SEC-22, PHASES.md S1/P1.
return \SJ\Core\Config::all();

<?php error_reporting( E_ALL );  ini_set('display_errors', 1);
function read($path){ 
  $r = '';
  $f = fopen($path, 'r');
  while($v = fread($f, 4096)) $r .= $v;
  fclose($f);
  return $r;
}

function import($v){
  $target = trim($v[1]);
  $file = trim($v[2]);
  if(array_search($file, $GLOBALS['dep']) == false) array_push($GLOBALS['dep'], $file);
  if($target[0] == '{'){
    foreach(explode(',', substr($target, 1, strlen($target) - 2)) as $v){
      if(strpos($v, 'as') == false){
        $v = trim($v);
        $GLOBALS['arg'] .= ','.$v;
        $GLOBALS['call'] .= ',_MOD_["'.$file.'"]["'.$v.'"]';
      }else{
        $v = explode('as', $v);
        $GLOBALS['arg'] .= ','.trim($v[1]);
        $GLOBALS['call'] .= ',_MOD_["'.$file.'"]["'.trim($target).'"]';
      }
    }
  }else{
    $GLOBALS['arg'] .= ','.$target;
    $GLOBALS['call'] .= ',_MOD_["'.$file.'"]._DEF_';
  }
  return '';
}
function exports($v){
  $r = '';
  foreach(explode(',', $v[1]) as $v){
    if(strpos($v, 'as') == false){
      $v = trim($v);
      $r .= ',_EX_["'.$v.'"] = '.$v;
    }else{
      $v = explode('as', $v);
      $r .= ',_EX_["'.trim($v[1]).'"] = '.trim($v[0]);
    }
  }
  return substr($r, 1).';';
}
$GLOBALS['files'] = array();
$GLOBALS['deps'] = array();
$stack = array($root = realpath(''));
$len = strlen($root);
$l = 0;
while(count($stack) > 0 && $l++ < 100){
  $c = array_pop($stack);
  foreach(scandir($c) as $v){
     if(strpos($v, '.js') != false){
      $v = $c.'\\'.$v;
      if(is_dir($v)) array_push($stack, $v);
      else{
        $key = substr($v, $len + 1);
        $jkey = substr($key, 0, -3);
        $GLOBALS['arg'] = $GLOBALS['call'] = '';
        $GLOBALS['file'][$key] = array();
        $GLOBALS['dep'] = array();
        $js = read($v);
        $js = preg_replace_callback('/(?:^|\s|;)import +(\S[\S\s]*?) +from +"(\S+)" *;/', 'import', $js);
        $js = preg_replace_callback('/(?:^|\s|;)export +\{ *(\S[\S\s]*) *\} *;/', 'exports', $js);
        $js = preg_replace('/(?:^|\s|;)export +default +(\S+);$/', '_EX_._DEF_=$1', $js);
        $js = '_MOD["'.$jkey.'"]=(function('.substr($GLOBALS['arg'], 1).'){var _EX_ = {};
        '.$js.'
return _EX_;})('.substr($GLOBALS['call'], 1).');';
        $GLOBALS['file'][$jkey] = $js;
        $GLOBALS['deps'][$jkey] = $GLOBALS['dep'];
      }
    }
  }
}
$order = array();
$l = 0;
while(count($GLOBALS['deps']) > 0 && $l++ < 100){
  foreach($GLOBALS['deps'] as $k => $v){
    if(count(array_intersect($v, $order)) == count($v)){
      array_push($order, $k);
      unset($GLOBALS['deps'][$k]);
    }
  }
}
$r = '(function(){var _MOD = {};
';
foreach($order as $v) $r .= $GLOBALS['file'][$v].'
';
echo $r.'})();';
?>

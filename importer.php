<?php 
error_reporting( E_ALL );  ini_set('display_errors', 1);

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
        $js = preg_replace_callback('/(?:^|\s|;)import +(\{[^}]+\}|[^{}]+) +from +"(\S+)" *;/', 'import', $js);
        $js = preg_replace_callback('/(?:^|\s|;)export +\{ *([^}]*) *\} *;/', 'exports', $js);
        $js = preg_replace('/(?:^|\s|;)export +default +([a-zA-Z$_][a-zA-Z0-9$_]*) *;/', '_EX_._DEF_=$1', $js);
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

//es6 기본처리
$reg = array(
  "/(^|\s|;)(let|const)(\s+)/"
);
$rep = array(
  "$1var$3"
);
//화살표함수 처리 https://regex101.com/r/5hVZRy/2/
$arrow = '/(\([^()]*\)|[a-zA-Z][a-zA-Z0-9$_]*)=>\s*((\{(?:[^{}]|\s|(?3))*\})|(\((?:[^()]|\s|(?4))*\))|(?:[^()\n\r;,](?:\([^()\n\r;]*\))?)*)/';
function arrow($v){
  $body = '';
  if(isset($v[3]) && $v[3] != '') $body = $v[3];
  else if(isset($v[2]) && $v[2] != '') $body = '{return '.$v[2].';}';
  else if(isset($v[4]) && $v[4] != '') $body = '{return '.$v[4].';}';
  return 'function'.($v[1][0] == '(' ? $v[1] : '('.$v[1].')').$body;
}
//클래스 처리 https://regex101.com/r/1uPfXk/3
$cls = '/class((?:\s+[A-Za-z$_][a-zA-Z0-9$_]*)?(?:\s+extends\s+[A-Za-z$_][a-zA-Z0-9$_]*)?)\s*\{((?:(\{(?:[^{}]|\s|(?3))*\})|[^{}])*)\}/';
function cls($v){
  $r = '(function(){var _C=function@conName@(@conArg@)@conBody@,_F=_C.prototype@super@;@methods@return _C})()';
  $name = trim($v[1]);
  $super = '';
  if($name != ''){
    $i = strpos($v[1], ' extends ');
    if($i !== false){
      $super = trim(substr($name, $i + strlen(' extends')));
      $name = trim(substr($name, 0, $i));
    }
  }
  $r = str_replace('@conName@', $name == '' ? '' : ' '.$name, $r);
  $r = str_replace('@super@', $super == '' ? '' : '=Object.create('.$super.'.prototype)', $r);
  $body = $v[2];
  $conArg = $conBody = '';
  $s = strpos($body, 'constructor');
  if($s !== false){
    $con = explode('@@@', preg_replace('/constructor\s*\(([^)]*)\)\s*(\{(?:[^{}]|\s|(?2))*\})/', '$1@@@$2', substr($body, $s)));
    $conArg = trim($con[0]);
    $conBody = trim($con[1]);
    $i = $k = 0;
    $j = strlen($conBody);
    while($i < $j){
      $c = $conBody[$i++];
      if($c == '{') $k++;
      else if($c == '}') $k--;
      if($k == 0) break;
    }
    $body = trim(substr($body, 0, $s - 1).substr($conBody, $i + 1));
    $conBody = trim(substr($conBody, 0, $i));
    $conBody = preg_replace('/(?:^|\s|;)super\(([^)]*)\)/', $super.'.call(this, $1)', $conBody);
  }
  $r = str_replace('@conArg@', $conArg, $r);
  $r = str_replace('@conBody@', $conBody, $r);
  $method = '';
  if($body != ''){
    $s = strpos($body, 'static ');
    $a = 0;
    while($s !== false && $a++<100){
      $i = strpos($body, '{', $s);
      $k = 0;
      $j = strlen($body);
      while($i < $j){
        $c = $body[$i++];
        if($c == '{') $k++;
        else if($c == '}') $k--;
        if($k == 0) break;
      }
      $con = trim(substr($body, $s + 7, $i - $s - 1));
      $body = substr($body, 0, $s).substr($body, $i + 1);
      
      $k = strpos($con, '(');
      
      $con = '_C.'.trim(substr($con, 0, $k)).'=function'.trim(substr($con, $k)).';';
      $con = preg_replace('/super((?:\.[a-zA-Z$_][0-9a-zA-Z$_]*)|(?:\[(?:\'\w+\'|"\w+")\])\([^)]*\))/', $super.'$1', $con);
      $method .= $con;
      $s = strpos($body, 'static ');
    }
    $s = strpos($body, '{');
    $a = 0;    
    while($s !== false && $a++<100){
      $i = $s;
      $k = 0;
      $j = strlen($body);
      while($i < $j){
        $c = $body[$i++];
        if($c == '{') $k++;
        else if($c == '}') $k--;
        if($k == 0) break;
      }
      $con = trim(substr($body, 0, $i));
      $body = substr($body, $i + 1);
      $con = '_F.'.trim(substr($con, 0, strpos($con, '('))).'=function '.trim(substr($con, $k)).';';
      $con = preg_replace('/super((?:\.[a-zA-Z$_][0-9a-zA-Z$_]*)|(?:\[(?:\'\w+\'|"\w+")\]))\(([^)]*)\)/', $super.'.prototype$1.call(this,$2)', $con);
      $method .= $con;
      $s = strpos($body, '{');
    }
    $r = str_replace('@methods@', $method, $r);
  }
  return $r;
};
foreach($order as $v){
  $js = $GLOBALS['file'][$v];
  $js = preg_replace($reg, $rep, $js);
  $js = preg_replace_callback($cls, 'cls', $js);
  
  $js = preg_replace_callback($arrow, 'arrow', $js);
  
  $r .= $js.'
';
}
echo $r.'})();';
?>

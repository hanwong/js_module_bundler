## JS 모듈 번들러
기존 번들러의 번잡함을 제거하고 적당한 범위에서 고속의 번들결과물을 만들어주는 것을 목표로함.

## 허용되는 문법

### import

```js
//parsing rex
/(?:^|\s|;)import +(\S[\S\s]*?) +from +"(\S+)" *;/

//default import
import name from "module"

//destructuring import
import {a, b, c} from "module"

//destructuring import with as
import {a, b as k, c} from "module"
```

### export

```js
//parsing rex
/(?:^|\s|;)export +default +(\S+);$/
/(?:^|\s|;)export +\{ *(\S[\S\s]*) *\} *;/

//default export
export default variableName;

//multi export
export {a, b, c};

//multi export with as
export {a, b as k, c};
```
export문에 직접 변수를 선언하거나 객체를 대입하는 등의 문법은 지원하지 않음

## 사용방법
js모듈이 모여있는 폴더 루트에 importer.php를 카피해 넣고 스크립트 상에서는 importer.php를 불러서 사용함

```html
<script src="path/importer.php"></script>
```

* 향후 로직이 안정화되면 파일 출력기 추가 ^^;

하위 폴더 전체의 모든 js를 검색하여 처리함.

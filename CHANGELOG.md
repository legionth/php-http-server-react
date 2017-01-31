# Changelog

## 0.5.0 (2017-01-31)

* Fix missing termination of connection in `sendResponse`method
  (#32 by legionth)

* Typo in middleware section
  (#30 by WyriHaximus)

* Fix missing sendResponse method
  (#29 by legionth)

* Streaming requests
  (#27 by legionth)

## 0.4.0 (2017-01-23)

* Protect TCP connection against `close` from other streams
  (#26 by @legionth)

* Filter `Content-Length` requests
  (#25 by @legionth)

* Add .gitignore 
  (#23 by @legionth)

* Remove HeaderDecoder from HttpServer
  (#21 by @legionth)

## 0.3.0 (2017-01-06)

* Add HTTP body streaming
  (#16 by @legionth)

* Add HTTP middleware support
  (#12 by @legionth)

## 0.2.0 (2016-12-12)

* Allow `React\Promise\Promise` as return type for the callback function
  (#11 by @legionth)

* Adding simple server example
  (#10 by @legionth)

## 0.1.1 (2016-12-02)

* Updated composer
  (by @legionth)

## 0.1.0 (2016-12-02)

* Handle wrong return types returned by callback function
  (#9 by @legionth)

* Add README 
  (#8 by @legionth)

* Send 500 internal server error on occuring exception
  (#6 by @legionth)

* Better header removement 
  (#5 by @legionth)

* Use mock of connection class instead of stub
  (#1 by @legionth)

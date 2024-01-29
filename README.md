<a name="readme-top"></a>

<!-- PROJECT SHIELDS -->
<!--
[![Contributors][contributors-shield]][contributors-url]
[![Forks][forks-shield]][forks-url]
[![Stargazers][stars-shield]][stars-url]
[![Issues][issues-shield]][issues-url]
[![MIT License][license-shield]][license-url]
[![LinkedIn][linkedin-shield]][linkedin-url]
<br />
-->


<!-- PROJECT LOGO -->
<div align="center">
  <a href="https://github.com/auntiewarhol/mvcish">
  </a>

<h1 align="center">MVCish</h1>
  <p align="center">
	Lightweight MVC-style PHP web application framework
  </p>
</div>

<!-- TABLE OF CONTENTS -->
<!--
<details>
  <summary>Table of Contents</summary>
  <ol>
    <li>
      <a href="#about-the-project">About The Project</a>
    </li>
    <li>
      <a href="#getting-started">Getting Started</a>
      <ul>
        <li><a href="#prerequisites">Prerequisites</a></li>
        <li><a href="#installation">Installation</a></li>
      </ul>
    </li>
    <li><a href="#usage">Usage</a></li>
    <li><a href="#roadmap">Roadmap</a></li>
    <li><a href="#contributing">Contributing</a></li>
    <li><a href="#license">License</a></li>
    <li><a href="#contact">Contact</a></li>
    <li><a href="#acknowledgments">Acknowledgments</a></li>
  </ol>
</details>
-->
<br>

<!-- ABOUT THE PROJECT -->
## About The Project

This is a super-lightweight (and thus probably feature-poor) MVC framework in the same vein as something like Yii. 

It exists because I inherited a large old legacy system that I needed to migrate, update, and replace. I wanted something I could easily hack into place, converting the legacy pages to new mvc-controlled pages in place, as needed, over time. 

So this has some capacities to run in multiple different ways at once.

None of this is really documented, because I wrote it for my own use.

It does not have any nifty installer scripts or anything like that. Yet.

And I don't really encourage anyone else to use it. But hey, it's a free world. If you like it, go for it. 

I'll try to work on the docs.


<p align="right">(<a href="#readme-top">back to top</a>)</p>



<!-- GETTING STARTED -->
<!--
## Getting Started


### Prerequisites

-->
### Installation

```
	composer require auntiewarhol/mvcish
```

<p align="right">(<a href="#readme-top">back to top</a>)</p>



<!-- USAGE EXAMPLES -->
## Usage

(here's a few things slurped in from comments. it's a start)


for typical single-point-of-entrance use, your app bootstrap script will be something like:

websiteroot/MyApp/myApp.php

```
<?php
  use \AuntieWarhol\MVCish\MVCish;


  # outside scope of MVCish but we recommend using this with csrf-magic
  # which can be used with beforeRender as shown below, so here's 
  # some setup for that, otherwise unrelated to MVCish

  if (php_sapi_name() != "cli") {

    // instruct PHP to use secure cookie if possible
    // honestly don't remember if for MVCish of csrf or why,
    // but leaving here for now at least...
    $secure = ((!empty($_SERVER['HTTPS'])) && $_SERVER['HTTPS'] !== 'off') ||
        (isset($_SERVER['SERVER_PORT']) && ($_SERVER['SERVER_PORT'] == 443)) ||
	    (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https')
	  ? true : false;
    session_set_cookie_params(
      ini_get('session.cookie_lifetime'),
      ini_get('session.cookie_path'),
      ini_get('session.cookie_domain'),
      $secure,
      true
    );

    // only do this after setting cookie params
    //function csrf_startup() {
      # configure csrf magic as needed
    //}
    require_once(__DIR__.'/../lib/csrf-magic-1.0.4/csrf-magic.php');
  }

  $MVCish = new \AuntieWarhol\MVCish\MVCish([
    'environment'  => ENV,
    'appDirectory' => __DIR__,

	// We recommend using Propel for the Model
    'MODEL' => [
      'NAMESPACE' => '\Models\\',
      'INIT'      => function($MVCish) {

        require_once __DIR__.'/Propel/generated-conf/config.php';

        if (!empty($serviceContainer)) {
          $serviceContainer->setLogger('defaultLogger', $MVCish->log('Propel'));
          $serviceContainer->setLogger('myApp', $MVCish->log());
        }

        // get the dummy connection config propel created,
        // replace with the values from our config

        $db = $MVCish->Config('DATABASE');
        $connection = \Propel\Runtime\Propel::getConnectionManager('myApp');
        // @phpstan-ignore-next-line
        $config = $connection->getConfiguration();
        // @phpstan-ignore-next-line
        $connection->setConfiguration(array_replace($config,[
          'dsn'      => 'mysql:host='.$db['HOST'].';dbname='.$db['NAME'],
          'user'     => $db['USER'],
          'password' => $db['PASS']
        ]));
      },
    ],

    'beforeRender' => function($MVCish) {

      if (function_exists('csrf_conf')) {
        if ($MVCish->View()->view() == 'html') {

          if (($name  = $GLOBALS['csrf']['input-name']) &&
            ($token = \BF\CSRFMagic::getTokens())
          ) {
            // stash csrf vars where Render can find them. Render will add them 
            // to body_attr, where our js can pick them up to add to ajax calls.
            $MVCish->options['CSRF'] = [
              'data-sectkn-name'  => $name,
              'data-sectkn-token' => $token
            ];
          }
        }
        else {
          //turn off csfr writing
          csrf_conf('rewrite',false);
        }
      }
      return true;
    }
  ]);

  // provide our Auth object to MVCish
  $MVCish->Auth(new \MyApp\Auth($MVCish));

  // If not included by another file, run now
  if(get_included_files()[0] == __FILE__) {
    $MVCish->Run();
  }
?>
```

Legacy file-in-place use would work similarly. Go into the legacy file, construct an $MVCish object as above, 
but instead of Run(), call runController() as described below. Whatever logic the legacy file was doing would 
then be moved to being done inside the closure being passed to runController.



#CONTROLLER ********************


we may or may not have a single point of entrance;
if we do, we figured out from the url what controller you wanted
and ran it. Otherwise, the url took you directly to a php file as
usual, and that file calls runController, passing the 'controller' as a closure:

```
$MVCish->runController(function() {
  #do controller stuff

  $response = //
    /*
		Response is super flexible. To do it proper, return a Response object:

		$response = $self->Response(); // $self in a controller is $MVCish.

		You can set things after:
		$response->success(true);
		$response->messageSuccess('It worked! Go you!');
		$response->data('something',$toPass);

		Or when creating:
		$response = $self->Response([
			'success'  => true,
			'messages' => ['success' => 'This also worked!'],
			'data'     => ['something' => $toPass,'somethingElse' => $toAlsoPass]
		]);

		In many cases, you'll just take the one you can get back from the form validator:

		$response = $self->Validator()->Response(
			// Validator is a whole other thing to document, but here's a taste...

			'title'        => ['required' => true, 'valid'=>['maxlength'=>100]],
			'instructions' => ['valid'=>['maxlength' =>	$self->Model('Foo')::MAX_FOO_INSTRUCTIONS]],
			'widgets'      => [
				'required' => $someCondition ? true : false,
				'valid'    => function($validator,&$value) use (&$self,&$User) {
					if (!is_array($value)) $value = [$value];
					if (count($value) == 1 && in_array($value[0],['none','all'])) return true;
					if ($self->Model('WidgetQuery')->filterByUser($User)->filterByActive(true)
						->filterByWidgetId($value)->count() == count($value)
					) return true;
					return false;
				},
				'missing' => "Please select which widgets will be used."
			],
			'writable' => ['defaulter' => 'boolfalse'],
			'expires' => [
				'default' => null,
				'valid'=>['date_format_to_iso' => 'm/d/Y'],
				'name'=>'Expiration Date'
			],
		]);

		(If all that passes the validator, $response->success() is now true, $response->valid() 
		will give you the validated form fields, etc. Otherwisem success would be false, 
		$response->missing() and $respons->invalid() will give you arrays of errored fields, etc.)

		---
		
		But you can also just send an array, with at minimum a 'success' key, eg:
		$response = ['success' => true];

		along with any other keys appropriate for the situation. 
		$response = ['success' => true, 'data' => ['something' => $toPass]];

		Or it could just be a bool, in which case we'll convert it
		$response = true;

		For that matter, we'll take any evaluates-true response you send.
		for example other than the typical array, you might send an object
		that can serialize itself for the json view.
		$response = $myJSONobject;

		Or even a text string that's the actual body of the response for the client.
		$response = "Not sure the use case, but there probably is one";
	*/

  return $response;
});

```


#MODEL ************************

'Model' is only very loosely coupled; just looks for the requested class,
Can configure to auto-prepend part of a namespace.
examples:

	$user = $MVCish->Model('\Models\UserQuery'); // returns '\Models\UserQuery'
	$user = $MVCish->Model('UserQuery');         // same, if 'Models\' configured as MODEL_NAMESPACE

A model_initialize function can be passed in MVCish options to do any
setup work needed for the model when MVCish starts. See myApp.php above.

ROADMAP: Updates coming to allow access to multiple models


#VIEW ************************

Currently defined Views:

```
  'html' => true, 'json' => true, 'stream' => true,
  'csv' => true, 'csvexcel' => true, 'xml' => true,
  'text' => true
```


if the configured or default templateDirectory is MyApp/MVCish/templates,
look for primary controller templates by default in MyApp/MVCish/templates/controllers,
while fragments or master tempates may be in MyApp/MVCish/templates/otherdirs.

default does not use a master template, just renders the controller template.
but subclasses may wish to define a master template into which the controller
html will be inserted.


The client application can use (pre-loaded or autoloadable) subclasses. This allows the 
application to create a renderClass that prepares template data and/or provides a master template.
App can have a subclass for each site section where these things might be
different (home pages vs account pages vs admin pages, etc).
The class is provided via MVCish->options, so if there's just one, it
could be provided in app-config.php, or if there are multiple, then
a controller can set the one it uses in Run options.
The class may be defined like:

    namespace \MyApp\Render;
    class Account extends \AuntieWarhol\MVCish\View\Render {
       ...override/add methods as needed to render an Account template
    }

 and then Controller uses like:
    $MVCish->Run(function($self){
       ...my controller code
    },[
       'renderClass' => '\MyApp\Render\Account'
    ]);


ROADMAP: Updates coming to make proper objects of each of the View types, so it
will be easier for clients to override / extend the built-in ones, or create new ones.


#AUTHORIZATION ************************

MVCish doesn't know anything about Authentication/Authorization.
but if you set an object on $MVCish->Auth() (see myApp.php above), and that object has
an "Authorize" method, we'll call it before running the controller, and pass it anything 
passed as an 'Authorize' option. The method should return true if authorized. If it returns 
false, we'll throw an unauthorized exception. Your object can also throw its own 
\AuntieWarhol\MVCish\Exception if you want to control the messaging 
(or throw Forbidden instead of Unauthorized, etc)

MyApp/lib/Auth.php
```
<?php
  namespace MyApp;

  class Auth {
    public function Authorize($authorizeRoles) {
      #do whatever
      #if bad
      return false; #or throw exception
      #if ok
      return true;
    }
  }
}?>
```	

ROADMAP: I don't really think Auth is something the *MVC* framework should be responsible for, 
but I can see an argument that an *application framework* is bigger than just the MVC, and should 
handle Auth. Will explore possibility of addding plugin capabilities, and maybe creating a 
PHPAuth (https://github.com/PHPAuth/PHPAuth"php::auth) Plugin. Or making it part of the coming 
install script, perhaps.


<p align="right">(<a href="#readme-top">back to top</a>)</p>


<!-- ROADMAP -->
## Roadmap

I have been SUPER busy the last couple of weeks, and at this point I believe I've fixed 90% of 
what I knew to be hacky and/or unfinished. I've got a little work left to do on Models and Views, 
as mentioned above, but I *think* that's about it for cleanup and pre-work.

I am about to use this in a second project for the first time, and I'm going to try and use the 
process to develop an install / build-starter-site script. And improve the docs. 

Once I've done those things, I think at that point, this might be ready for public consumption, 
if anyone is inclined to play with it.

Future stuff, as I mentioned I think a Plugin architechture might be the next step, to add things 
that I don't think are MVC-framework responsibilities, but might be Application-framework responsibilities. 
Auth is the first big one, and then *maybe* HTML builder tools?


(boilerplate)
See the [open issues](https://github.com/auntiewarhol/mvcish/issues) for a full list of proposed features (and known issues).

<p align="right">(<a href="#readme-top">back to top</a>)</p>


<!-- CONTRIBUTING -->
## Contributing

You probably shouldn't be using this, much less contributing to it. But if you insist, I will leave these instructions as provided by this template I'm using, because hey, why not.

--

If you have a suggestion that would make this better, please fork the repo and create a pull request. You can also simply open an issue with the tag "enhancement".
Don't forget to give the project a star! Thanks again!

1. Fork the Project
2. Create your Feature Branch (`git checkout -b feature/AmazingFeature`)
3. Commit your Changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the Branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

<p align="right">(<a href="#readme-top">back to top</a>)</p>



<!-- LICENSE -->
## License

Distributed under the MIT License. See `LICENSE.txt` for more information.

<p align="right">(<a href="#readme-top">back to top</a>)</p>



<!-- CONTACT -->
## Contact

Project Link: [https://github.com/auntiewarhol/mvcish](https://github.com/auntiewarhol/mvcish)

<p align="right">(<a href="#readme-top">back to top</a>)</p>



<!-- ACKNOWLEDGMENTS -->
<!--
## Acknowledgments


<p align="right">(<a href="#readme-top">back to top</a>)</p>
-->


<!-- MARKDOWN LINKS & IMAGES -->
<!-- https://www.markdownguide.org/basic-syntax/#reference-style-links -->
[contributors-shield]: https://img.shields.io/github/contributors/auntiewarhol/mvcish.svg?style=for-the-badge
[contributors-url]: https://github.com/auntiewarhol/mvcish/graphs/contributors
[forks-shield]: https://img.shields.io/github/forks/auntiewarhol/mvcish.svg?style=for-the-badge
[forks-url]: https://github.com/auntiewarhol/mvcish/network/members
[stars-shield]: https://img.shields.io/github/stars/auntiewarhol/mvcish.svg?style=for-the-badge
[stars-url]: https://github.com/auntiewarhol/mvcish/stargazers
[issues-shield]: https://img.shields.io/github/issues/auntiewarhol/mvcish.svg?style=for-the-badge
[issues-url]: https://github.com/auntiewarhol/mvcish/issues
[license-shield]: https://img.shields.io/github/license/auntiewarhol/mvcish.svg?style=for-the-badge
[license-url]: https://github.com/auntiewarhol/mvcish/blob/master/LICENSE.txt
[linkedin-shield]: https://img.shields.io/badge/-LinkedIn-black.svg?style=for-the-badge&logo=linkedin&colorB=555
[linkedin-url]: https://linkedin.com/in/jawright
[product-screenshot]: images/screenshot.png
[Next.js]: https://img.shields.io/badge/next.js-000000?style=for-the-badge&logo=nextdotjs&logoColor=white
[Next-url]: https://nextjs.org/
[React.js]: https://img.shields.io/badge/React-20232A?style=for-the-badge&logo=react&logoColor=61DAFB
[React-url]: https://reactjs.org/
[Vue.js]: https://img.shields.io/badge/Vue.js-35495E?style=for-the-badge&logo=vuedotjs&logoColor=4FC08D
[Vue-url]: https://vuejs.org/
[Angular.io]: https://img.shields.io/badge/Angular-DD0031?style=for-the-badge&logo=angular&logoColor=white
[Angular-url]: https://angular.io/
[Svelte.dev]: https://img.shields.io/badge/Svelte-4A4A55?style=for-the-badge&logo=svelte&logoColor=FF3E00
[Svelte-url]: https://svelte.dev/
[Laravel.com]: https://img.shields.io/badge/Laravel-FF2D20?style=for-the-badge&logo=laravel&logoColor=white
[Laravel-url]: https://laravel.com
[Bootstrap.com]: https://img.shields.io/badge/Bootstrap-563D7C?style=for-the-badge&logo=bootstrap&logoColor=white
[Bootstrap-url]: https://getbootstrap.com
[JQuery.com]: https://img.shields.io/badge/jQuery-0769AD?style=for-the-badge&logo=jquery&logoColor=white
[JQuery-url]: https://jquery.com 

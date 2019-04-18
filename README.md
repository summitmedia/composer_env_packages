# Composer Environment Packages

Composer Environment Packages is a plugin which adds ability for adding environment specific composer packages. 
Most common use case are:

  - add different version of packages per environment
  - add additional packages per environment
  - test some functionality, which requires specific packages or different versions of them. 

The plugin can determine environment by:
  - git branch (current and parent)
  - ENV variable which is widely used nowadays at shared hosting platforms.
  - user input. 

## Settings and using.

First of all you need to require this plugin add settings in extra section of your composer file.
Settings example:

```sh
 "extra": {
      "environment-dependencies": {
        "check-host-env": false,
        "host-env-variable": "AH_SITE_ENVIRONMENT",
        "check-git-env": false,
        "ask-question": true,
        "git-env": {
            "develop": "composer.develop.json",
            "master": "composer.master.json"
        },
        "host-env-map": {
            "dev": "develop",
            "test": "master",
            "prod": "master",
            "default": "master"
        }
    }
 }
```
Then you should create the appropriate composer file for each environment that you added in git-env settings.
In this file, you can add environment specific repositories and requirements.

### Settings explanation:
> check-host-env

Values: true,false. If this is true, the plugin will determine environment in $_ENV variable. To use it you should also add "host-env-variable" and "host-env-map" settings.

> host-env-variable

 Values: string. The name of environment variable. E.g. on Acquia this is "AH_SITE_ENVIRONMENT", on Platform.sh is "PLATFORM_BRANCH"
 
 > check-git-env
 
 Values: true,false. If this is true, the plugin will determine environment by current or parent git branch. 
 
 > ask-question
 
 Values: true,false. If this is true, the plugin determine the environment by user input. This is a fallback option if the plugin can't determene environment by git branch or hosting environment variable.
 
 > git-env: {}
 
 This is the main plugin setting. You should add here environment name and appropriate composer file. `Pay attention to environment name, because this name is also used as a git branch name.` 
 
 > host-env-map {}
 
 This is a map between hosting environment name and `git-env` name. You can use "default" option here. The default option will be used if the current environment a not mapped in the host-env-map setting.
 
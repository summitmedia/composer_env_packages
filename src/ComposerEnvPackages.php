<?php

namespace PMG\Composer;

use Composer\Composer;
use Composer\Factory;
use Composer\Plugin\PluginInterface;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Script\Event;
use Composer\IO\IOInterface;
use Composer\Script\ScriptEvents;

/**
 * Adds environment specific dependencies to composer.
 */
class ComposerEnvPackages implements PluginInterface, EventSubscriberInterface {

  /**
   * @var Composer $composer
   */
  protected $composer;
  /**
   * @var IOInterface $io
   */
  protected $io;

  /**
   * Apply plugin modifications to composer
   *
   * @param Composer    $composer
   * @param IOInterface $io
   */
  public function activate(Composer $composer, IOInterface $io) {
    $this->composer = $composer;
    $this->io = $io;
    $this->extra = $this->composer->getPackage()->getExtra();
  }

  /**
   * Returns an array of event names this subscriber wants to listen to.
   */
  public static function getSubscribedEvents() {
    return array(
      ScriptEvents::PRE_INSTALL_CMD => array('preUpdate'),
      ScriptEvents::PRE_UPDATE_CMD => array('preUpdate'),
    );
  }

  /**
   * Merges repositories and requirements from an environment specific composer file.
   *
   * @param Event $event The event being fired.
   */
  public function preUpdate(Event $event) {
    // Do not process if environment-dependencies section is empty.
    if (empty($this->extra['environment-dependencies'])) {
      return;
    }

    // Pulls plugin settings.
    $settings = $this->extra['environment-dependencies'];
    $git_env = array_keys($settings['git-env']);
    // Determines environment by git branch. If current branch
    // is not in git-env map it will also check in parent branches.
    if ($settings['check-git-env'] === TRUE) {
      exec("git rev-parse --abbrev-ref HEAD", $branch);
      if (count($branch) > 0) {
        if (in_array($branch[0], $git_env)) {
          $env = array_pop($branch);
        }
        else {
          $command = 'git show-branch |
                      grep "*" |
                      grep -v "$(git rev-parse --abbrev-ref HEAD)" |
                      head -n1 |
                      sed "s/.*\[\(.*\)\].*/\1/" |
                      sed "s/[\^~].*//"';
          exec($command, $parent_branch);

          if (in_array($parent_branch[0], $git_env)) {
            $env = array_pop($parent_branch);
          }
        }
      }
    }

    // Determines environment by host environment variable.
    if (
      !empty($settings['check-host-env']) &&
      !empty($settings['host-env-variable']) &&
      $settings['check-host-env'] === TRUE &&
      isset($_ENV[$settings['host-env-variable']])
    ) {
      $host_env = $_ENV[$settings['host-env-variable']];
      if (!empty($settings['host-env-map'][$host_env])) {
        $env = $settings['host-env-map'][$host_env];
      }
      else if (!empty($settings['host-env-map']['default'])) {
        $env = $settings['host-env-map']['default'];
      }
    }

    // Determines the environment by user input. This is a fallback option.
    if (empty($env) && $settings['ask-question'] === TRUE) {
      while (TRUE) {
        $allowed_env_str = implode(",", $git_env);
        $answer = $this->io->ask("<info>Please select which composer dependencies should be used: [{$allowed_env_str}]?</info>");
        if (in_array($answer, $git_env)) {
          $env = $answer;
          break;
        }
        else {
          $this->io->writeError('<error>Wrong input.</error>');
        }
      }
    }

    // If the environment is not detected do not continue the code execution.
    if (empty($env)) {
      $this->io->writeError("<error>Can't determine environment. Check your settings please.</error>");
      return;
    }

    // Info message about detected environment.
    $this->io->write("<info>Current environment is {$env}. The {$env} dependencies will be used.</info>");

    // Checks if environment file exist.
    if (!empty($settings['git-env'][$env])) {
      $env_dependencies_file = $settings['git-env'][$env];
      if (!file_exists($env_dependencies_file)) {
        $this->io->writeError("<error>{$env} dependencies are added. But {$env_dependencies_file} file doesn't exist.</error>");
        return;
      }
    }
    else {
      $this->io->write("<info>The {$env} dependencies are not defined. Skipping. </info>");
      return;
    }

    $factory = new Factory();

    // Creates new composer object for environment dependencies.
    $env_composer = $factory->createComposer(
      $event->getIO(),
      $env_dependencies_file,
      true,
      null,
      false
    );

    // Merge repositories.
    $repositories = array_merge($this->composer->getPackage()->getRepositories(), $env_composer->getPackage()->getRepositories());
    if (method_exists($this->composer->getPackage(), 'setRepositories')) {
      $this->composer->getPackage()->setRepositories($repositories);
    }
    // Merge requirements.
    $requires = array_merge($this->composer->getPackage()->getRequires(), $env_composer->getPackage()->getRequires());
    $this->composer->getPackage()->setRequires($requires);
    $devRequires = array_merge($this->composer->getPackage()->getDevRequires(), $env_composer->getPackage()->getDevRequires());
    $this->composer->getPackage()->setDevRequires($devRequires);

  }
}

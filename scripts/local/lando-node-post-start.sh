#!/bin/bash

###############################################################
#  These commands need to be run as normal user from lando.yml.
#
#  NOTE: THIS SCRIPT SHOULD BE RUN INSIDE THE CONTAINER.
#
#  These commands install Start the npm processes: gulp and stylus.
#
#  PRE-REQUISITES:
#     - node:10 docker container for patterns is created and started, and
#     - main boston.gov repo is already cloned onto the host machine, and
#     - .lando.yml and .config.yml files are correctly configured.
#
# THIS IS A DOCKER COMMAND FILE, IT WILL ONLY RUN UPTO THE FIRST COMMAND WHICH
# DOES NOT TERMINATE AND THEN WILL RUN NO FURTHER UNTIL THE CONATINER IS STOPPED.
#
#  Basic workflow:
#     1. Start the fractal service.
###############################################################

  printout "SCRIPT" "starts <$(basename $BASH_SOURCE)>"
  printf "\n"

  # Include the utilities file/libraries.
  # This causes the .lando.yml and .config.yml files to be read in and stored as variables.
  REPO_ROOT="${LANDO_MOUNT}"
  . "${LANDO_MOUNT}/scripts/cob_build_utilities.sh"
  . "${LANDO_MOUNT}/scripts/deploy/cob_utilities.sh"
  target_env="local"

  printout "SCRIPT" "starts <$(basename $BASH_SOURCE)>"
  printf "\n"

  if [[ ! -d ${patterns_local_repo_local_dir} ]]; then
    printf "No folder ${patterns_local_repo_local_dir}\n";
    # CONTAINER STOPS HERE.
    tail -f /dev/null ;
    exit 0;
  fi

  printf "\n${Blue}       ================================================================================${NC}\n"
  printout "LANDO" "Project Event - patterns post-start\n"
  printf "${Blue}       ================================================================================${NC}\n"

  # Install the patterns app.
  if [[ "${patterns_local_build}" != "true" ]] && [[ "${patterns_local_build}" != "True" ]] && [[ "${patterns_local_build}" != "TRUE" ]]; then
      printout "INFO" "Patterns library will not be deployed."
  else
      printout "INFO" "Starting Patterns library - will build stylus(css) and minify js files."
      printout "ACTION" "Wait for files."
      # Because the node container builds after the database and appserver containers, we have to
      # wait for thoseprocesses to complete first.  TODO: Make appserver and database dependent on the node server.
      while [[ ! -e  ${patterns_local_repo_local_dir}/public/css ]]; do
          sleep 10
      done
  fi

  # SCRIPT PAUSES HERE UNTIL CONTAINER IS STOPPED.
  # Fire up the watchers: Note this process will remain running until the container is stopped.
  # (and the container will stop if this process terminates for any reason)
  printout "ACTION" "Create the fractal server and start watch file system for updates."
  cd ${webapps_local_local_dir} && npm run start-container

  printout "SCRIPT" "ends <$(basename $BASH_SOURCE)>"
  printf "\n"

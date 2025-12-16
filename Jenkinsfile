pipeline {
    agent any

    options {
        disableConcurrentBuilds()
        timestamps()
        timeout(time: 60, unit: 'MINUTES')
    }

    environment {
        GIT_REPO              = "https://github.com/Anandreddy125/project-management.git"
        GIT_CREDENTIALS_ID    = "terra-github"
        DOCKER_CREDENTIALS_ID = "anand-dockerhub"
    }

    parameters {
        choice(
            name: 'BRANCH_PARAM',
            choices: ['staging', 'master'],
            description: 'Used ONLY for manual builds'
        )
        booleanParam(
            name: 'ROLLBACK',
            defaultValue: false,
            description: 'Rollback to TARGET_VERSION'
        )
        string(
            name: 'TARGET_VERSION',
            defaultValue: '',
            description: 'Docker tag for rollback'
        )
    }

    /*
      pollSCM does NOT build every 5 minutes.
      It only triggers when a new tag is pushed.
      You may replace this with GitHub webhook later.
    */
    triggers {
        githubPush()
        pollSCM('H/5 * * * *')
    }

    stages {

        /* ---------------- CLEAN ---------------- */
        stage('Clean Workspace') {
            steps { cleanWs() }
        }

        /* ---------------- CHECKOUT ---------------- */
        stage('Checkout Code') {
            steps {
                script {

                    // TAG BUILD
                    if (env.GIT_BRANCH?.startsWith('refs/tags/')) {

                        env.IS_TAG_BUILD = "true"
                        env.BUILD_TAG   = env.GIT_BRANCH.replace('refs/tags/', '')
                        env.ACTUAL_BRANCH = "master"

                        echo "🏷️ Tag-triggered build detected: ${env.BUILD_TAG}"

                        checkout([
                            $class: 'GitSCM',
                            branches: [[name: "refs/tags/${env.BUILD_TAG}"]],
                            userRemoteConfigs: [[
                                url: env.GIT_REPO,
                                credentialsId: env.GIT_CREDENTIALS_ID
                            ]]
                        ])

                    } else {

                        // MANUAL / BRANCH BUILD
                        env.IS_TAG_BUILD = "false"
                        def branchName = env.BRANCH_NAME ?: params.BRANCH_PARAM
                        env.ACTUAL_BRANCH = branchName

                        echo "🔄 Branch build: ${branchName}"

                        checkout([
                            $class: 'GitSCM',
                            branches: [[name: "*/${branchName}"]],
                            userRemoteConfigs: [[
                                url: env.GIT_REPO,
                                credentialsId: env.GIT_CREDENTIALS_ID
                            ]]
                        ])
                    }
                }
            }
        }

        /* ---------------- ENVIRONMENT ---------------- */
        stage('Determine Environment') {
            steps {
                script {

                    if (env.IS_TAG_BUILD == "true") {
                        env.DEPLOY_ENV = "production"
                        env.IMAGE_NAME = "anrs125/reports-tesing"
                        env.TAG_TYPE   = "release"

                    } else if (env.ACTUAL_BRANCH == "staging") {
                        env.DEPLOY_ENV = "staging"
                        env.IMAGE_NAME = "anrs125/reports-tesing"
                        env.TAG_TYPE   = "commit"

                    } else if (env.ACTUAL_BRANCH == "master") {
                        env.DEPLOY_ENV = "production"
                        env.IMAGE_NAME = "anrs125/reports-tesing"
                        env.TAG_TYPE   = "release"

                    } else {
                        error("Unsupported branch: ${env.ACTUAL_BRANCH}")
                    }

                    echo """
==============================
Environment Info
==============================
Branch       : ${env.ACTUAL_BRANCH}
Deploy Env   : ${env.DEPLOY_ENV}
Image Repo   : ${env.IMAGE_NAME}
Tag Type     : ${env.TAG_TYPE}
Tag Build    : ${env.IS_TAG_BUILD}
Build Tag    : ${env.BUILD_TAG ?: 'N/A'}
==============================
"""
                }
            }
        }

        /* ---------------- DOCKER TAG ---------------- */
        stage('Generate Docker Tag') {
            steps {
                script {

                    if (params.ROLLBACK) {

                        if (!params.TARGET_VERSION?.trim()) {
                            error("Rollback requested but TARGET_VERSION not provided")
                        }
                        env.IMAGE_TAG = params.TARGET_VERSION.trim()

                    } else if (env.IS_TAG_BUILD == "true") {

                        env.IMAGE_TAG = env.BUILD_TAG
                        echo "🏷️ Using Git tag as Docker tag: ${env.IMAGE_TAG}"

                    } else {

                        def commitId = sh(
                            script: "git rev-parse --short HEAD",
                            returnStdout: true
                        ).trim()

                        env.IMAGE_TAG = "staging-${commitId}"
                    }

                    echo "🚀 FINAL Docker Tag: ${env.IMAGE_TAG}"
                }
            }
        }

        /* ---- Docker build / push / deploy stages can follow ---- */

    }

    post {

        success {
            script {
                def buildType = env.IS_TAG_BUILD == "true" ? "Tag Release" : "Manual Build"

                slackSend(
                    channel: 'C09M08HUK8W',
                    color: '#36A64F',
                    tokenCredentialId: 'slack-token',
                    message: """
:white_check_mark: *${buildType} Successful*
Env   : ${env.DEPLOY_ENV}
Image : ${env.IMAGE_NAME}:${env.IMAGE_TAG}
Tag   : ${env.BUILD_TAG ?: 'N/A'}
<${env.BUILD_URL}|View Build>
"""
                )
            }
        }

        failure {
            script {
                slackSend(
                    channel: 'C09M08HUK8W',
                    color: '#FF0000',
                    tokenCredentialId: 'slack-token',
                    message: ":x: *Build Failed* <${env.BUILD_URL}|View Logs>"
                )
            }
        }

        always {
            echo 'Pipeline completed.'
            cleanWs()
        }
    }
}

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

        DEPLOY_ENV            = "production"
        IMAGE_NAME            = "anrs125/reports-tesing"
    }

    parameters {
        choice(
            name: 'BRANCH_PARAM',
            choices: ['master'],
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
      pollSCM triggers ONLY when a new tag is pushed.
      Safe for production.
    */
    triggers {
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

                    /* ---------- TAG BUILD ---------- */
                    if (env.GIT_BRANCH?.startsWith('refs/tags/')) {

                        env.IS_TAG_BUILD  = "true"
                        env.BUILD_TAG     = env.GIT_BRANCH.replace('refs/tags/', '')
                        env.ACTUAL_BRANCH = "master"

                        echo "🏷️ Tag-triggered production build: ${env.BUILD_TAG}"

                        checkout([
                            $class: 'GitSCM',
                            branches: [[name: "refs/tags/${env.BUILD_TAG}"]],
                            userRemoteConfigs: [[
                                url: env.GIT_REPO,
                                credentialsId: env.GIT_CREDENTIALS_ID
                            ]]
                        ])

                    } else {


                        env.IS_TAG_BUILD  = "false"
                        env.ACTUAL_BRANCH = "master"

                        echo "🔄 Manual master build"

                        checkout([
                            $class: 'GitSCM',
                            branches: [[name: "*/master"]],
                            userRemoteConfigs: [[
                                url: env.GIT_REPO,
                                credentialsId: env.GIT_CREDENTIALS_ID
                            ]]
                        ])
                    }
                }
            }
        }

        stage('Determine Environment') {
            steps {
                script {
                    env.TAG_TYPE = "release"

                    echo """
==============================
Environment Info
==============================
Branch       : ${env.ACTUAL_BRANCH}
Deploy Env   : ${env.DEPLOY_ENV}
Image Repo   : ${env.IMAGE_NAME}
Tag Type     : release
Tag Build    : ${env.IS_TAG_BUILD}
Build Tag    : ${env.BUILD_TAG ?: 'N/A'}
==============================
"""
                }
            }
        }
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

                        error("Manual production build requires Git tag")
                    }

                    echo "🚀 FINAL Docker Tag: ${env.IMAGE_TAG}"
                }
            }
        }
    }

    post {

        success {
            script {
                def buildType = env.IS_TAG_BUILD == "true" ? "Production Tag Release" : "Manual Production Build"

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
            slackSend(
                channel: 'C09M08HUK8W',
                color: '#FF0000',
                tokenCredentialId: 'slack-token',
                message: ":x: *Production Build Failed* <${env.BUILD_URL}|View Logs>"
            )
        }

        always {
            echo 'Pipeline completed.'
            cleanWs()
        }
    }
}

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

    /* 🔥 Webhook-based trigger */
    triggers {
        githubPush()
    }

    stages {

        /* ---------------- CLEAN ---------------- */
        stage('Clean Workspace') {
            steps { cleanWs() }
        }

        /* ---------------- VALIDATE TRIGGER ---------------- */
        stage('Validate Trigger') {
            steps {
                script {

                    // TAG push → allow
                    if (env.GIT_BRANCH?.startsWith('refs/tags/')) {
                        echo "✅ Tag trigger detected: ${env.GIT_BRANCH}"
                        return
                    }

                    // staging branch push → allow
                    if (env.GIT_BRANCH == 'origin/staging' || env.GIT_BRANCH == 'staging') {
                        echo "✅ Staging branch trigger detected"
                        return
                    }

                    // manual build → allow
                    if (currentBuild.rawBuild.getCause(hudson.model.Cause$UserIdCause)) {
                        echo "✅ Manual build detected"
                        return
                    }

                    error("""
❌ Build blocked!

Allowed triggers:
 - git push origin staging
 - git push origin <tag>

Blocked trigger:
 - ${env.GIT_BRANCH}
""")
                }
            }
        }

        /* ---------------- CHECKOUT ---------------- */
        stage('Checkout Code') {
            steps {
                script {

                    // TAG BUILD → PRODUCTION
                    if (env.GIT_BRANCH?.startsWith('refs/tags/')) {

                        env.IS_TAG_BUILD   = "true"
                        env.BUILD_TAG     = env.GIT_BRANCH.replace('refs/tags/', '')
                        env.ACTUAL_BRANCH = "master"

                        echo "🏷️ Tag build: ${env.BUILD_TAG}"

                        checkout([
                            $class: 'GitSCM',
                            branches: [[name: "refs/tags/${env.BUILD_TAG}"]],
                            userRemoteConfigs: [[
                                url: env.GIT_REPO,
                                credentialsId: env.GIT_CREDENTIALS_ID
                            ]]
                        ])

                    } else {

                        // STAGING BUILD
                        env.IS_TAG_BUILD   = "false"
                        env.ACTUAL_BRANCH = "staging"

                        echo "🔄 Staging branch build"

                        checkout([
                            $class: 'GitSCM',
                            branches: [[name: "*/staging"]],
                            userRemoteConfigs: [[
                                url: env.GIT_REPO,
                                credentialsId: env.GIT_CREDENTIALS_ID
                            ]]
                        ])
                    }
                }
            }
        }

        /* ---------------- ENV ---------------- */
        stage('Determine Environment') {
            steps {
                script {

                    if (env.IS_TAG_BUILD == "true") {
                        env.DEPLOY_ENV = "production"
                        env.TAG_TYPE   = "release"
                    } else {
                        env.DEPLOY_ENV = "staging"
                        env.TAG_TYPE   = "commit"
                    }

                    env.IMAGE_NAME = "anrs125/reports-tesing"

                    echo """
==============================
Environment Info
==============================
Branch     : ${env.ACTUAL_BRANCH}
Env        : ${env.DEPLOY_ENV}
Image Repo : ${env.IMAGE_NAME}
Tag Build  : ${env.IS_TAG_BUILD}
Build Tag  : ${env.BUILD_TAG ?: 'N/A'}
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
                        echo "🏷️ Using Git tag: ${env.IMAGE_TAG}"

                    } else {

                        def commitId = sh(
                            script: "git rev-parse --short HEAD",
                            returnStdout: true
                        ).trim()

                        env.IMAGE_TAG = "staging-${commitId}"
                    }

                    echo "🚀 FINAL IMAGE TAG: ${env.IMAGE_TAG}"
                }
            }
        }

        /* ---- Docker build / push / deploy stages go here ---- */

    }

    post {

        success {
            script {
                slackSend(
                    channel: 'C09M08HUK8W',
                    color: '#36A64F',
                    tokenCredentialId: 'slack-token',
                    message: """
:white_check_mark: *Deployment Successful*
Env   : ${env.DEPLOY_ENV}
Image : ${env.IMAGE_NAME}:${env.IMAGE_TAG}
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

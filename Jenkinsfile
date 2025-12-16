pipeline {
    agent any

    options {
        disableConcurrentBuilds()
        timestamps()
        timeout(time: 60, unit: 'MINUTES')
    }

    environment {
        GIT_REPO           = "https://github.com/Anandreddy125/project-management.git"
        GIT_CREDENTIALS_ID = "terra-github"
        IMAGE_NAME         = "anrs125/reports-tesing"
    }

    triggers {
        githubPush()
    }

    stages {

        stage('Clean Workspace') {
            steps { cleanWs() }
        }

        /* 🔐 SINGLE SOURCE OF TRUTH */
        stage('Validate Trigger') {
            steps {
                script {

                    echo "GIT_BRANCH = ${env.GIT_BRANCH}"

                    // TAG → PRODUCTION
                    if (env.GIT_BRANCH?.startsWith('refs/tags/')) {
                        env.DEPLOY_ENV   = "production"
                        env.IS_TAG_BUILD = "true"
                        env.BUILD_TAG    = env.GIT_BRANCH.replace('refs/tags/', '')
                        return
                    }

                    // STAGING → STAGING ONLY
                    if (env.GIT_BRANCH == 'refs/heads/staging'
                        || env.GIT_BRANCH == 'origin/staging'
                        || env.GIT_BRANCH == 'staging') {

                        env.DEPLOY_ENV   = "staging"
                        env.IS_TAG_BUILD = "false"
                        return
                    }

                    // 🚫 EVERYTHING ELSE BLOCKED
                    error("""
❌ Build blocked!

Allowed:
 - git push origin staging
 - git push origin <tag>

Blocked ref:
 - ${env.GIT_BRANCH}
""")
                }
            }
        }

        /* 🔄 CHECKOUT ONLY WHAT IS ALLOWED */
        stage('Checkout Code') {
            steps {
                script {

                    def refToCheckout = ""

                    if (env.IS_TAG_BUILD == "true") {
                        refToCheckout = "refs/tags/${env.BUILD_TAG}"
                    } else {
                        refToCheckout = "refs/heads/staging"
                    }

                    echo "Checking out: ${refToCheckout}"

                    checkout([
                        $class: 'GitSCM',
                        branches: [[name: refToCheckout]],
                        userRemoteConfigs: [[
                            url: env.GIT_REPO,
                            credentialsId: env.GIT_CREDENTIALS_ID
                        ]]
                    ])
                }
            }
        }

        stage('Generate Docker Tag') {
            steps {
                script {

                    if (env.IS_TAG_BUILD == "true") {
                        env.IMAGE_TAG = env.BUILD_TAG
                    } else {
                        def commit = sh(
                            script: "git rev-parse --short HEAD",
                            returnStdout: true
                        ).trim()
                        env.IMAGE_TAG = "staging-${commit}"
                    }

                    echo "Image: ${env.IMAGE_NAME}:${env.IMAGE_TAG}"
                }
            }
        }

        /* ---- Docker build & deploy here ---- */

    }

    post {
        success {
            echo "✅ ${env.DEPLOY_ENV.toUpperCase()} deployment successful"
        }
        failure {
            echo "❌ Build failed"
        }
        always {
            cleanWs()
        }
    }
}

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
    }

    triggers {
        githubPush()
    }

    stages {

        stage('Clean Workspace') {
            steps { cleanWs() }
        }

        stage('Checkout Code') {
            steps {
                checkout([
                    $class: 'GitSCM',
                    branches: [[name: "**"]],
                    userRemoteConfigs: [[
                        url: env.GIT_REPO,
                        credentialsId: env.GIT_CREDENTIALS_ID
                    ]]
                ])
            }
        }

        /* 🔒 SINGLE SOURCE OF TRUTH */
        stage('Detect Trigger Type') {
            steps {
                script {

                    // Detect TAG
                    def tag = sh(
                        script: "git describe --tags --exact-match 2>/dev/null || true",
                        returnStdout: true
                    ).trim()

                    // Detect BRANCH
                    def branch = sh(
                        script: "git rev-parse --abbrev-ref HEAD",
                        returnStdout: true
                    ).trim()

                    echo "Detected branch: ${branch}"
                    echo "Detected tag   : ${tag ?: 'none'}"

                    if (tag) {
                        // ✅ PRODUCTION
                        env.IS_TAG_BUILD = "true"
                        env.BUILD_TAG    = tag
                        env.DEPLOY_ENV   = "production"

                    } else if (branch == "staging") {
                        // ✅ STAGING
                        env.IS_TAG_BUILD = "false"
                        env.DEPLOY_ENV   = "staging"

                    } else {
                        error("""
❌ Build blocked!

Allowed:
 - git push origin staging
 - git push origin <tag>

Blocked branch:
 - ${branch}
""")
                    }
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

                    env.IMAGE_NAME = "anrs125/reports-tesing"
                    echo "Final image: ${env.IMAGE_NAME}:${env.IMAGE_TAG}"
                }
            }
        }

        /* ---- Docker build / deploy ---- */

    }

    post {
        success {
            echo "✅ ${env.DEPLOY_ENV} deployment successful"
        }
        failure {
            echo "❌ Build failed"
        }
        always {
            cleanWs()
        }
    }
}

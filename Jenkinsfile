pipeline {
    agent any

    triggers {
        githubPush()
    }

    options {
        disableConcurrentBuilds()
        timestamps()
        timeout(time: 60, unit: 'MINUTES')
    }

    environment {
        SCANNER_HOME          = tool('sonar-scanner')
        GIT_REPO              = "https://github.com/Anandreddy125/project-management.git"
        GIT_CREDENTIALS_ID    = "terra-github"
        DOCKER_CREDENTIALS_ID = "anand-dockerhub"
    }

    parameters {
        booleanParam(name: 'ROLLBACK', defaultValue: false, description: 'Rollback to TARGET_VERSION')
        string(name: 'TARGET_VERSION', defaultValue: '', description: 'Rollback version')
    }

    stages {

        /* -------------------------------------------
           BLOCK unwanted builds
           Only allow:
             ✔ staging branch
             ✔ tag builds (v1.0.0)
        -------------------------------------------- */
        stage('Validate Build Trigger') {
            steps {
                script {

                    env.ACTUAL_BRANCH = sh(
                        script: "git rev-parse --abbrev-ref HEAD",
                        returnStdout: true
                    ).trim()

                    env.GIT_TAG = sh(
                        script: "git describe --tags --exact-match HEAD 2>/dev/null || echo ''",
                        returnStdout: true
                    ).trim()

                    echo "Detected branch=${env.ACTUAL_BRANCH}, tag=${env.GIT_TAG}"

                    // ❌ If main branch build WITHOUT tag → block it
                    if (env.ACTUAL_BRANCH == "main" && !env.GIT_TAG) {
                        error """
❌ Production Build Blocked

You pushed to *main* but did NOT create a version tag.

✔ Allowed actions:
   - Push to staging branch
   - Push a tag (v1.0.0) on main

To deploy production:
    git tag -a v1.0.0 -m "Release"
    git push origin v1.0.0
"""
                    }

                    // ✔ Allowed: staging or tag
                    echo "✔ Build allowed. Continuing..."
                }
            }
        }

        stage('Clean Workspace') {
            steps { cleanWs() }
        }

        stage('Checkout Code') {
            steps {
                script {
                    checkout scm
                }
            }
        }

        stage('Determine Environment') {
            steps {
                script {

                    if (env.GIT_TAG) {
                        env.DEPLOY_ENV = "production"
                        env.IMAGE_NAME = "anrs125/farhan-testing"
                        env.KUBERNETES_CREDENTIALS_ID = "k3s-report-staging1"
                        env.DEPLOYMENT_FILE = "prod-reports.yaml"
                        env.DEPLOYMENT_NAME = "prod-reports-api"
                        env.TAG_TYPE = "release"

                    } else if (env.ACTUAL_BRANCH == "staging") {

                        env.DEPLOY_ENV = "staging"
                        env.IMAGE_NAME = "anrs125/sample-private"
                        env.KUBERNETES_CREDENTIALS_ID = "reports-staging1"
                        env.DEPLOYMENT_FILE = "staging-report.yaml"
                        env.DEPLOYMENT_NAME = "staging-reports-api"
                        env.TAG_TYPE = "commit"

                    } else {
                        error "❌ Invalid trigger. Only staging and tags allowed."
                    }
                }
            }
        }

        stage('Generate Docker Tag') {
            steps {
                script {

                    if (params.ROLLBACK) {
                        env.IMAGE_TAG = params.TARGET_VERSION.trim()
                        return
                    }

                    if (env.TAG_TYPE == "release") {
                        env.IMAGE_TAG = env.GIT_TAG
                        return
                    }

                    def commitId = sh(script: "git rev-parse --short HEAD", returnStdout: true).trim()
                    env.IMAGE_TAG = "staging-${commitId}"
                }
            }
        }

        stage('Docker Login') {
            steps {
                script {
                    withCredentials([usernamePassword(credentialsId: env.DOCKER_CREDENTIALS_ID,
                        usernameVariable: 'USER', passwordVariable: 'PASS')]) {
                        sh "echo ${PASS} | docker login -u ${USER} --password-stdin"
                    }
                }
            }
        }

        stage('Docker Build & Push') {
            steps {
                script {
                    def fullImage = "${env.IMAGE_NAME}:${env.IMAGE_TAG}"
                    sh """
                        docker build -t ${fullImage} .
                        docker push ${fullImage}
                    """
                }
            }
        }
    }
}

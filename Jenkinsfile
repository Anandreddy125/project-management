pipeline {
    agent any

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
        choice(name: 'BUILD_TYPE', choices: ['AUTO', 'STAGING', 'PRODUCTION'], description: 'AUTO = detect from branch')
        booleanParam(name: 'ROLLBACK', defaultValue: false, description: 'Rollback to TARGET_VERSION')
        string(name: 'TARGET_VERSION', defaultValue: '', description: 'Rollback version')
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
                script {
                    def branchName = env.BRANCH_NAME ?: "staging"

                    echo "🔹 Detected Branch: ${branchName}"

                    checkout scm
                    env.ACTUAL_BRANCH = branchName

                    // Detect Git tag on this commit
                    env.GIT_TAG = sh(
                        script: "git describe --tags --exact-match HEAD 2>/dev/null || echo ''",
                        returnStdout: true
                    ).trim()

                    echo "🔹 Detected Git Tag: ${env.GIT_TAG ?: 'NO TAG'}"

                    // BUILD TYPE AUTO LOGIC
                    if (params.BUILD_TYPE == "AUTO") {
                        env.BUILD_TYPE = (branchName == "main") ? "PRODUCTION" : "STAGING"
                    } else {
                        env.BUILD_TYPE = params.BUILD_TYPE
                    }

                    echo "🔹 Final Build Type: ${env.BUILD_TYPE}"
                }
            }
        }

        stage('Determine Environment') {
            steps {
                script {

                    /* -------------------------
                       STAGING ENVIRONMENT
                       ------------------------- */
                    if (env.BUILD_TYPE == 'STAGING') {

                        env.DEPLOY_ENV = "staging"
                        env.IMAGE_NAME = "anrs125/sample-private"
                        env.KUBERNETES_CREDENTIALS_ID = "reports-staging1"
                        env.DEPLOYMENT_FILE = "staging-report.yaml"
                        env.DEPLOYMENT_NAME = "staging-reports-api"
                        env.TAG_TYPE = "commit"

                    /* -------------------------
                       PRODUCTION ENVIRONMENT
                       ------------------------- */
                    } else if (env.BUILD_TYPE == 'PRODUCTION') {

                        if (!env.GIT_TAG) {
                            error """
❌ PRODUCTION DEPLOY BLOCKED — NO TAG FOUND

To deploy:
  git tag -a v1.0.0 -m "Release"
  git push origin v1.0.0
"""
                        }

                        // Optional: enforce proper version tag
                        if (!(env.GIT_TAG ==~ /^v[0-9]+\\.[0-9]+\\.[0-9]+$/)) {
                            error """
❌ INVALID PRODUCTION TAG: ${env.GIT_TAG}

Production requires semantic version:
  ✔ v1.0.0
  ✔ v2.3.4
"""
                        }

                        env.DEPLOY_ENV = "production"
                        env.IMAGE_NAME = "anrs125/farhan-testing"
                        env.KUBERNETES_CREDENTIALS_ID = "k3s-report-staging1"
                        env.DEPLOYMENT_FILE = "prod-reports.yaml"
                        env.DEPLOYMENT_NAME = "prod-reports-api"
                        env.TAG_TYPE = "release"

                    } else {
                        error "❌ Unknown BUILD_TYPE: ${env.BUILD_TYPE}"
                    }

                    echo """
============================
      BUILD CONFIG
============================
Build Type      : ${env.BUILD_TYPE}
Branch          : ${env.ACTUAL_BRANCH}
Environment     : ${env.DEPLOY_ENV}
Docker Repo     : ${env.IMAGE_NAME}
Deployment Name : ${env.DEPLOYMENT_NAME}
Deployment File : ${env.DEPLOYMENT_FILE}
Git Tag         : ${env.GIT_TAG}
============================
"""
                }
            }
        }

        stage('Generate Docker Tag') {
            steps {
                script {

                    if (params.ROLLBACK) {
                        if (!params.TARGET_VERSION?.trim()) {
                            error "Rollback requested but TARGET_VERSION missing!"
                        }
                        env.IMAGE_TAG = params.TARGET_VERSION.trim()
                        echo "Rollback version selected: ${env.IMAGE_TAG}"
                        return
                    }

                    if (env.TAG_TYPE == "commit") {  // STAGING ONLY
                        def commitId = sh(script: "git rev-parse --short HEAD", returnStdout: true).trim()
                        env.IMAGE_TAG = "staging-${commitId}"
                        echo "Staging Tag: ${env.IMAGE_TAG}"
                        return
                    }

                    if (env.TAG_TYPE == "release") { // PRODUCTION ONLY
                        env.IMAGE_TAG = env.GIT_TAG
                        echo "Production Tag: ${env.IMAGE_TAG}"
                    }
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
                    echo "🐳 Building image: ${fullImage}"

                    sh """
                        docker build -t ${fullImage} .
                        docker push ${fullImage}
                    """
                }
            }
        }
    }
}

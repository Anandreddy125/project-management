pipeline {
    agent any

    /* 
     * Prevent Jenkins from running on unwanted branches.
     * This pipeline only runs when:
     *   ✔ a push happens in "staging"
     *   ✔ a TAG is pushed (v1.0.0 etc.)
     */
    triggers {
        githubPush()
    }

    when {
        anyOf {
            branch 'staging'
            buildingTag()    // Production ONLY when tag exists
        }
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

        stage('Clean Workspace') {
            steps { cleanWs() }
        }

        stage('Checkout Code') {
            steps {
                script {
                    checkout scm

                    // Detect branch
                    env.ACTUAL_BRANCH = sh(
                        script: "git rev-parse --abbrev-ref HEAD",
                        returnStdout: true
                    ).trim()

                    // Detect Git tag for production
                    env.GIT_TAG = sh(
                        script: "git describe --tags --exact-match HEAD 2>/dev/null || echo ''",
                        returnStdout: true
                    ).trim()

                    echo "🔹 Branch: ${env.ACTUAL_BRANCH}"
                    echo "🔹 Git Tag: ${env.GIT_TAG ?: 'NO TAG'}"
                }
            }
        }

        stage('Determine Environment') {
            steps {
                script {

                    /* -----------------------------------
                       PRODUCTION (TAG-BASED ONLY)
                    ----------------------------------- */
                    if (env.GIT_TAG) {

                        echo "✔ Production tag detected → ${env.GIT_TAG}"

                        if (!(env.GIT_TAG ==~ /^v[0-9]+\\.[0-9]+\\.[0-9]+$/)) {
                            error """
❌ INVALID PRODUCTION TAG: ${env.GIT_TAG}

Production tags must follow semantic versioning:
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
                    }

                    /* -----------------------------------
                       STAGING (staging branch)
                    ----------------------------------- */
                    else if (env.ACTUAL_BRANCH == "staging") {

                        env.DEPLOY_ENV = "staging"
                        env.IMAGE_NAME = "anrs125/sample-private"
                        env.KUBERNETES_CREDENTIALS_ID = "reports-staging1"
                        env.DEPLOYMENT_FILE = "staging-report.yaml"
                        env.DEPLOYMENT_NAME = "staging-reports-api"
                        env.TAG_TYPE = "commit"
                    }

                    else {
                        error """
❌ Blocked: This pipeline only runs on:
  ✔ staging branch
  ✔ Git tags (v1.0.0)
"""
                    }

                    echo """
==================== DEPLOY CONFIG ====================
Environment     : ${env.DEPLOY_ENV}
Branch          : ${env.ACTUAL_BRANCH}
Docker Repo     : ${env.IMAGE_NAME}
Deployment File : ${env.DEPLOYMENT_FILE}
Deployment Name : ${env.DEPLOYMENT_NAME}
Tag Detected    : ${env.GIT_TAG}
=======================================================
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
                        echo "Rollback Version → ${env.IMAGE_TAG}"
                        return
                    }

                    // Production uses Git Tag
                    if (env.TAG_TYPE == "release") {
                        env.IMAGE_TAG = env.GIT_TAG
                        echo "Production Tag → ${env.IMAGE_TAG}"
                        return
                    }

                    // Staging commit-based tag
                    def commitId = sh(script: "git rev-parse --short HEAD", returnStdout: true).trim()
                    env.IMAGE_TAG = "staging-${commitId}"

                    echo "Staging Tag → ${env.IMAGE_TAG}"
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
                    echo "🐳 Building image → ${fullImage}"

                    sh """
                        docker build -t ${fullImage} .
                        docker push ${fullImage}
                    """
                }
            }
        }
    }
}

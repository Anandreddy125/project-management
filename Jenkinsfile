pipeline {
    agent any

    options {
        disableConcurrentBuilds()
        timestamps()
        timeout(time: 60, unit: 'MINUTES')
        skipDefaultCheckout(true)
    }

    environment {
        GIT_REPO              = "https://github.com/Anandreddy125/project-management.git"
        GIT_CREDENTIALS_ID    = "terra-github"
        DOCKER_CREDENTIALS_ID = "anand-dockerhub"
        IMAGE_NAME            = "anrs125/reports-tesing"
    }

    parameters {
        booleanParam(
            name: 'ROLLBACK',
            defaultValue: false,
            description: 'Rollback using TARGET_VERSION'
        )
        string(
            name: 'TARGET_VERSION',
            defaultValue: '',
            description: 'Docker tag for rollback'
        )
    }

    stages {

        /* ---------------- CLEAN ---------------- */
        stage('Clean Workspace') {
            steps { cleanWs() }
        }

        /* ---------------- CHECKOUT ---------------- */
        stage('Checkout Code') {
            steps {
                checkout([
                    $class: 'GitSCM',
                    branches: [[name: "${env.GIT_BRANCH}"]],
                    userRemoteConfigs: [[
                        url: env.GIT_REPO,
                        credentialsId: env.GIT_CREDENTIALS_ID,
                        refspec: '+refs/heads/*:refs/remotes/origin/* +refs/tags/*:refs/remotes/origin/tags/*'
                    ]]
                ])
            }
        }

        /* ---------------- DETECT BRANCH OR TAG ---------------- */
        stage('Detect Ref Type') {
            steps {
                script {
                    echo "GIT_BRANCH = ${env.GIT_BRANCH}"

                    if (env.GIT_BRANCH.startsWith("origin/tags/")) {
                        env.TAG_TYPE = "release"
                        env.TAG_NAME = env.GIT_BRANCH.replace("origin/tags/", "")
                        env.DEPLOY_ENV = "production"
                        env.DEPLOYMENT_FILE = "prod-reports.yaml"
                        env.DEPLOYMENT_NAME = "prod-reports-api"
                    }
                    else if (env.GIT_BRANCH == "origin/staging") {
                        env.TAG_TYPE = "commit"
                        env.DEPLOY_ENV = "staging"
                        env.DEPLOYMENT_FILE = "staging-report.yaml"
                        env.DEPLOYMENT_NAME = "staging-reports-api"
                    }
                    else {
                        error("Unsupported branch or ref: ${env.GIT_BRANCH}")
                    }

                    echo """
                    ===== Deployment Info =====
                    Ref Type   : ${env.TAG_TYPE}
                    Environment: ${env.DEPLOY_ENV}
                    Tag Name   : ${env.TAG_NAME ?: 'N/A'}
                    ===========================
                    """
                }
            }
        }

        /* ---------------- GENERATE DOCKER TAG ---------------- */
        stage('Generate Docker Tag') {
            steps {
                script {
                    if (params.ROLLBACK) {
                        if (!params.TARGET_VERSION?.trim()) {
                            error("Rollback requested but TARGET_VERSION is empty")
                        }
                        env.IMAGE_TAG = params.TARGET_VERSION.trim()
                    }
                    else if (env.TAG_TYPE == "release") {
                        env.IMAGE_TAG = env.TAG_NAME
                    }
                    else {
                        def commitId = sh(
                            script: "git rev-parse --short HEAD",
                            returnStdout: true
                        ).trim()
                        env.IMAGE_TAG = "staging-${commitId}"
                    }

                    echo "Docker Image Tag: ${env.IMAGE_TAG}"
                }
            }
        }

        /* ---------------- DOCKER LOGIN ---------------- */
        stage('Docker Login') {
            when { expression { !params.ROLLBACK } }
            steps {
                withCredentials([usernamePassword(
                    credentialsId: env.DOCKER_CREDENTIALS_ID,
                    usernameVariable: 'DOCKER_USER',
                    passwordVariable: 'DOCKER_PASS'
                )]) {
                    sh "echo $DOCKER_PASS | docker login -u $DOCKER_USER --password-stdin"
                }
            }
        }

        /* ---------------- BUILD & PUSH ---------------- */
        stage('Docker Build & Push') {
            when { expression { !params.ROLLBACK } }
            steps {
                script {
                    sh """
                        docker build -t ${IMAGE_NAME}:${IMAGE_TAG} .
                        docker push ${IMAGE_NAME}:${IMAGE_TAG}
                        docker logout
                    """
                }
            }
        }
    }

    post {
        success {
            echo "Deployment successful for ${DEPLOY_ENV}"
        }
        failure {
            echo "Deployment failed"
        }
    }
}

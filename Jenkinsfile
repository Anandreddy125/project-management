pipeline {
    agent any

    options {
        disableConcurrentBuilds()
        timestamps()
        timeout(time: 60, unit: 'MINUTES')
    }

    environment {
        GIT_CREDENTIALS_ID    = "terra-github"
        DOCKER_CREDENTIALS_ID = "anand-dockerhub"
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
            description: 'Docker image tag for rollback'
        )
    }

    stages {
        stage('Clean Workspace') {
            steps {
                cleanWs()
            }
        }

        stage('Checkout Code') {
            steps {
                checkout scm
                script {
                    echo "BRANCH_NAME = ${env.BRANCH_NAME}"
                    echo "TAG_NAME    = ${env.TAG_NAME ?: 'N/A'}"
                }
            }
        }

        stage('Determine Environment') {
            steps {
                script {

                    /* ---------- STAGING: normal branch push ---------- */
                    if (env.BRANCH_NAME == "staging" && !env.TAG_NAME) {

                        env.DEPLOY_ENV = "staging"
                        env.IMAGE_NAME = "anrs125/reports-tesing"
                        env.KUBERNETES_CREDENTIALS_ID = "reports-staging"
                        env.DEPLOYMENT_FILE = "staging-report.yaml"
                        env.DEPLOYMENT_NAME = "staging-reports-api"
                        env.TAG_TYPE = "commit"

                    /* ---------- PRODUCTION: tag push on master ---------- */
                    } else if (env.TAG_NAME) {

                        env.DEPLOY_ENV = "production"
                        env.IMAGE_NAME = "anrs125/reports-tesing"
                        env.KUBERNETES_CREDENTIALS_ID = "k3s-report-staging"
                        env.DEPLOYMENT_FILE = "prod-reports.yaml"
                        env.DEPLOYMENT_NAME = "prod-reports-api"
                        env.TAG_TYPE = "release"

                    /* ---------- BLOCK MASTER WITHOUT TAG ---------- */
                    } else if (env.BRANCH_NAME == "master") {
                        error("""
❌ Direct master push detected.

Production deployments are allowed ONLY via tags.

Correct flow:
  git checkout master
  git merge staging
  git tag vX.Y.Z
  git push origin vX.Y.Z
""")
                    } else {
                        error("❌ Unsupported branch: ${env.BRANCH_NAME}")
                    }

                    echo """
=============================
 Environment Summary
=============================
 Branch     : ${env.BRANCH_NAME}
 Tag        : ${env.TAG_NAME ?: "N/A"}
 Deploy Env : ${env.DEPLOY_ENV}
 Image      : ${env.IMAGE_NAME}
 Deployment : ${env.DEPLOYMENT_NAME}
=============================
"""
                }
            }
        }

        stage('Generate Docker Tag') {
            steps {
                script {

                    if (params.ROLLBACK) {
                        if (!params.TARGET_VERSION?.trim()) {
                            error("Rollback requested but TARGET_VERSION is empty")
                        }
                        env.IMAGE_TAG = params.TARGET_VERSION.trim()

                    } else if (env.TAG_TYPE == "commit") {

                        def commitId = sh(
                            script: "git rev-parse --short HEAD",
                            returnStdout: true
                        ).trim()
                        env.IMAGE_TAG = "staging-${commitId}"

                    } else {
                        env.IMAGE_TAG = env.TAG_NAME
                    }

                    echo "🚀 FINAL IMAGE TAG: ${env.IMAGE_TAG}"
                }
            }
        }

        /* ---------------- DOCKER BUILD & PUSH ---------------- */
        stage('Docker Build & Push') {
            when { expression { !params.ROLLBACK } }
            steps {
                withCredentials([
                    usernamePassword(
                        credentialsId: env.DOCKER_CREDENTIALS_ID,
                        usernameVariable: 'DOCKER_USER',
                        passwordVariable: 'DOCKER_PASS'
                    )
                ]) {
                    sh """
                        echo \$DOCKER_PASS | docker login -u \$DOCKER_USER --password-stdin
                        docker build --no-cache -t ${env.IMAGE_NAME}:${env.IMAGE_TAG} .
                        docker push ${env.IMAGE_NAME}:${env.IMAGE_TAG}
                        docker logout
                    """
                }
            }
        }
    }
}
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
        booleanParam(
            name: 'ROLLBACK',
            defaultValue: false,
            description: 'Rollback using TARGET_VERSION'
        )
        string(
            name: 'TARGET_VERSION',
            defaultValue: '',
            description: 'Docker tag to rollback'
        )
    }

    stages {

        /* ---------------- CLEAN ---------------- */
        stage('Clean Workspace') {
            steps {
                cleanWs()
            }
        }

        /* ---------------- CHECKOUT ---------------- */
        stage('Checkout Code') {
            steps {
                checkout scm
                script {
                    echo "BRANCH_NAME = ${env.BRANCH_NAME}"
                    echo "TAG_NAME    = ${env.TAG_NAME ?: 'N/A'}"
                }
            }
        }

        /* ---------------- ENV DECISION ---------------- */
        stage('Determine Environment') {
            steps {
                script {

                    /* ---------- STAGING (branch push) ---------- */
                    if (env.BRANCH_NAME == "staging" && !env.TAG_NAME) {

                        env.DEPLOY_ENV = "staging"
                        env.IMAGE_NAME = "anrs125/reports-tesing"
                        env.KUBERNETES_CREDENTIALS_ID = "reports-staging"
                        env.DEPLOYMENT_FILE = "staging-report.yaml"
                        env.DEPLOYMENT_NAME = "staging-reports-api"
                        env.TAG_TYPE = "commit"

                    /* ---------- PRODUCTION (tag push on master) ---------- */
                    } else if (env.TAG_NAME) {

                        env.DEPLOY_ENV = "production"
                        env.IMAGE_NAME = "anrs125/reports-tesing"
                        env.KUBERNETES_CREDENTIALS_ID = "k3s-report-staging"
                        env.DEPLOYMENT_FILE = "prod-reports.yaml"
                        env.DEPLOYMENT_NAME = "prod-reports-api"
                        env.TAG_TYPE = "release"

                    /* ---------- BLOCK MASTER PUSH WITHOUT TAG ---------- */
                    } else if (env.BRANCH_NAME == "master") {

                        error("""
❌ Direct master branch push detected.

Production deployments are allowed ONLY via tags.

Correct flow:
  git checkout master
  git merge staging
  git tag vX.Y.Z
  git push origin vX.Y.Z
""")

                    /* ---------- BLOCK EVERYTHING ELSE ---------- */
                    } else {
                        error("""
❌ Unsupported trigger

Branch : ${env.BRANCH_NAME}
Tag    : ${env.TAG_NAME ?: "N/A"}

Allowed:
✔ git push origin staging
✔ git push origin vX.Y.Z
""")
                    }

                    echo """
=============================
 Environment Summary
=============================
 Branch     : ${env.BRANCH_NAME}
 Tag        : ${env.TAG_NAME ?: "N/A"}
 Deploy Env : ${env.DEPLOY_ENV}
 Tag Type   : ${env.TAG_TYPE}
 Deployment : ${env.DEPLOYMENT_NAME}
=============================
"""
                }
            }
        }

        /* ---------------- TAG GENERATION ---------------- */
        stage('Generate Docker Tag') {
            steps {
                script {

                    def imageTag = ""

                    if (params.ROLLBACK) {

                        if (!params.TARGET_VERSION?.trim()) {
                            error("Rollback enabled but TARGET_VERSION is empty")
                        }
                        imageTag = params.TARGET_VERSION.trim()

                    } else if (env.TAG_TYPE == "commit") {

                        def commitId = sh(
                            script: "git rev-parse --short HEAD",
                            returnStdout: true
                        ).trim()
                        imageTag = "staging-${commitId}"

                    } else if (env.TAG_TYPE == "release") {

                        imageTag = env.TAG_NAME
                    }

                    env.IMAGE_TAG = imageTag
                    echo "🚀 FINAL DOCKER TAG: ${env.IMAGE_TAG}"
                }
            }
        }

        /* ---------------- DOCKER LOGIN ---------------- */
        stage('Docker Login') {
            steps {
                withCredentials([
                    usernamePassword(
                        credentialsId: env.DOCKER_CREDENTIALS_ID,
                        usernameVariable: 'DOCKER_USER',
                        passwordVariable: 'DOCKER_PASSWORD'
                    )
                ]) {
                    sh """
                        echo "${DOCKER_PASSWORD}" | docker login \
                        -u "${DOCKER_USER}" --password-stdin
                    """
                }
            }
        }

        /* ---------------- BUILD & PUSH ---------------- */
        stage('Docker Build & Push') {
            when {
                expression { return !params.ROLLBACK }
            }
            steps {
                script {
                    def imageFull = "${env.IMAGE_NAME}:${env.IMAGE_TAG}"
                    sh """
                        docker build --no-cache -t ${imageFull} .
                        docker push ${imageFull}
                        docker logout
                    """
                }
            }
        }
    }
}

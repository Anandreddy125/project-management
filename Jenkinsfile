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
        IMAGE_REPO            = "anrs125/reports-tesing"
    }

    stages {

        /* ================= CLEAN ================= */
        stage('Clean Workspace') {
            steps {
                cleanWs()
            }
        }

        /* ================= CHECKOUT ================= */
        stage('Checkout Code') {
            steps {
                checkout scm
                script {
                    echo "🔹 BRANCH_NAME = ${env.BRANCH_NAME}"
                    echo "🔹 TAG_NAME    = ${env.TAG_NAME ?: 'N/A'}"
                }
            }
        }

        /* ================= ENV DECISION ================= */
        stage('Determine Environment') {
            steps {
                script {

                    /* ---------- STAGING (branch push) ---------- */
                    if (env.BRANCH_NAME == "staging" && !env.TAG_NAME) {

                        env.DEPLOY_ENV = "staging"
                        env.TAG_TYPE   = "commit"

                    /* ---------- PRODUCTION (tag push) ---------- */
                    } else if (env.TAG_NAME) {

                        env.DEPLOY_ENV = "production"
                        env.TAG_TYPE   = "release"

                    /* ---------- BLOCK MASTER WITHOUT TAG ---------- */
                    } else if (env.BRANCH_NAME == "master") {

                        error("""
❌ Direct master push blocked.

Production deployments are allowed ONLY via Git tags.

Correct workflow:
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
 Deployment Summary
=============================
 Branch     : ${env.BRANCH_NAME}
 Tag        : ${env.TAG_NAME ?: "N/A"}
 Environment: ${env.DEPLOY_ENV}
=============================
"""
                }
            }
        }

        /* ================= IMAGE TAG ================= */
        stage('Generate Docker Tag') {
            steps {
                script {
                    if (env.TAG_TYPE == "commit") {

                        def commitId = sh(
                            script: "git rev-parse --short HEAD",
                            returnStdout: true
                        ).trim()

                        env.IMAGE_TAG = "staging-${commitId}"

                    } else {
                        env.IMAGE_TAG = env.TAG_NAME
                    }

                    echo "🚀 FINAL DOCKER IMAGE: ${env.IMAGE_REPO}:${env.IMAGE_TAG}"
                }
            }
        }

        /* ================= DOCKER BUILD & PUSH ================= */
        stage('Docker Build & Push') {
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
                        docker build --no-cache -t ${env.IMAGE_REPO}:${env.IMAGE_TAG} .
                        docker push ${env.IMAGE_REPO}:${env.IMAGE_TAG}
                        docker logout
                    """
                }
            }
        }
    }

    post {
        success {
            echo "✅ Deployment completed successfully"
        }
        failure {
            echo "❌ Deployment failed"
        }
        always {
            cleanWs()
        }
    }
}

//changed jenkinsfile
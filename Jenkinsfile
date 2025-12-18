pipeline {
    agent any

    options {
        disableConcurrentBuilds()
        timestamps()
        timeout(time: 60, unit: 'MINUTES')
    }

    environment {
        DOCKER_CREDENTIALS_ID = "anand-dockerhub"

        STAGING_IMAGE    = "anrs125/reports-staging"
        PRODUCTION_IMAGE = "anrs125/reports-staging"
    }

    stages {

        /* ===================== CONTEXT ===================== */
        stage('Detect Build Context') {
            steps {
                script {
                    env.IS_TAG = env.GIT_BRANCH?.startsWith("refs/tags/") ? "true" : "false"
                    env.TAG_NAME = env.IS_TAG == "true"
                        ? env.GIT_BRANCH.replace("refs/tags/", "")
                        : ""

                    echo """
                    ===========================
                    BRANCH_NAME : ${env.BRANCH_NAME}
                    GIT_BRANCH  : ${env.GIT_BRANCH}
                    IS_TAG      : ${env.IS_TAG}
                    TAG_NAME    : ${env.TAG_NAME}
                    ===========================
                    """

                    // 🚫 Block master branch push
                    if (env.BRANCH_NAME == "master" && env.IS_TAG == "false") {
                        error("❌ Master branch push blocked. Use TAG for production.")
                    }

                    // 🚫 Block unsupported branches
                    if (!["staging", "master"].contains(env.BRANCH_NAME) && env.IS_TAG == "false") {
                        error("❌ Unsupported branch: ${env.BRANCH_NAME}")
                    }
                }
            }
        }

        /* ===================== CLEAN ===================== */
        stage('Clean Workspace') {
            steps { cleanWs() }
        }

        /* ===================== CHECKOUT ===================== */
        stage('Checkout Code') {
            steps {
                checkout scm
            }
        }

        /* ===================== IMAGE TAG ===================== */
        stage('Generate Docker Tag') {
            steps {
                script {
                    def commitId = sh(
                        script: "git rev-parse --short HEAD",
                        returnStdout: true
                    ).trim()

                    if (env.IS_TAG == "true") {
                        env.IMAGE_NAME = env.PRODUCTION_IMAGE
                        env.IMAGE_TAG  = env.TAG_NAME
                        env.DEPLOY_ENV = "production"
                    } else {
                        env.IMAGE_NAME = env.STAGING_IMAGE
                        env.IMAGE_TAG  = "staging-${commitId}"
                        env.DEPLOY_ENV = "staging"
                    }

                    echo "🚀 IMAGE → ${env.IMAGE_NAME}:${env.IMAGE_TAG}"
                }
            }
        }

        /* ===================== DOCKER LOGIN ===================== */
        stage('Docker Login') {
            steps {
                withCredentials([
                    usernamePassword(
                        credentialsId: env.DOCKER_CREDENTIALS_ID,
                        usernameVariable: 'DOCKER_USER',
                        passwordVariable: 'DOCKER_PASS'
                    )
                ]) {
                    sh "echo $DOCKER_PASS | docker login -u $DOCKER_USER --password-stdin"
                }
            }
        }

        /* ===================== BUILD & PUSH ===================== */
        stage('Docker Build & Push') {
            steps {
                sh """
                    docker build -t ${IMAGE_NAME}:${IMAGE_TAG} .
                    docker push ${IMAGE_NAME}:${IMAGE_TAG}
                    docker logout
                """
            }
        }

        /* ===================== DEPLOY ===================== */
        stage('Deploy') {
            steps {
                script {
                    if (env.DEPLOY_ENV == "staging") {
                        echo "🧪 Deploying to STAGING"
                        // kubectl apply -f k8s/staging.yaml
                    }

                    if (env.DEPLOY_ENV == "production") {
                        echo "🚀 Deploying to PRODUCTION (TAG: ${TAG_NAME})"
                        // kubectl apply -f k8s/production.yaml
                    }
                }
            }
        }
    }

    post {
        success {
            echo "✅ Pipeline completed successfully"
        }
        failure {
            echo "❌ Pipeline failed"
        }
    }
}

//error
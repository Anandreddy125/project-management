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
    }

    stages {

        /* ---------------- CLEAN ---------------- */
        stage('Clean Workspace') {
            steps {
                cleanWs()
            }
        }

        /* ---------------- CHECKOUT ---------------- */
        stage('Checkout') {
            steps {
                checkout scm
                echo "Checked out ref: ${env.BRANCH_NAME}"
            }
        }

        /* ---------------- DETECT ENV ---------------- */
        stage('Detect Environment') {
            steps {
                script {

                    if (env.BRANCH_NAME == 'staging') {
                        env.DEPLOY_ENV = 'staging'
                        env.IMAGE_NAME = 'anrs125/reports-tesing'
                        env.DEPLOYMENT_FILE = 'staging-report.yaml'
                        env.DOCKER_TAG = "staging-${sh(script: 'git rev-parse --short HEAD', returnStdout: true).trim()}"

                    } 
                    else if (env.BRANCH_NAME == 'master') {
                        // ❌ Master branch should NOT auto-deploy
                        currentBuild.result = 'NOT_BUILT'
                        error('Master branch push detected — deployment skipped. Use tags for production.')

                    } 
                    else if (env.BRANCH_NAME.startsWith('v')) {
                        env.DEPLOY_ENV = 'production'
                        env.IMAGE_NAME = 'anrs125/reports-tesing'
                        env.DEPLOYMENT_FILE = 'prod-reports.yaml'
                        env.DOCKER_TAG = env.BRANCH_NAME
                    } 
                    else {
                        error("Unsupported ref: ${env.BRANCH_NAME}")
                    }

                    echo """
                    ===============================
                    Deployment Details
                    ===============================
                    Ref:         ${env.BRANCH_NAME}
                    Environment: ${env.DEPLOY_ENV}
                    Image:       ${env.IMAGE_NAME}
                    Tag:         ${env.DOCKER_TAG}
                    """
                }
            }
        }

        /* ---------------- DOCKER LOGIN ---------------- */
        stage('Docker Login') {
            steps {
                withCredentials([usernamePassword(
                    credentialsId: env.DOCKER_CREDENTIALS_ID,
                    usernameVariable: 'DOCKER_USER',
                    passwordVariable: 'DOCKER_PASSWORD'
                )]) {
                    sh "echo $DOCKER_PASSWORD | docker login -u $DOCKER_USER --password-stdin"
                }
            }
        }

        /* ---------------- BUILD & PUSH ---------------- */
        stage('Docker Build & Push') {
            steps {
                sh """
                    docker build --no-cache -t ${env.IMAGE_NAME}:${env.DOCKER_TAG} .
                    docker push ${env.IMAGE_NAME}:${env.DOCKER_TAG}
                    docker logout
                """
            }
        }

        /* ---------------- DEPLOY ---------------- */
        stage('Deploy to Kubernetes') {
            steps {
                sh """
                    sed -i 's|IMAGE_TAG|${env.DOCKER_TAG}|g' ${env.DEPLOYMENT_FILE}
                    kubectl apply -f ${env.DEPLOYMENT_FILE}
                """
            }
        }
    }

    post {
        success {
            echo "✅ Deployment successful for ${env.BRANCH_NAME}"
        }
        failure {
            echo "❌ Deployment failed for ${env.BRANCH_NAME}"
        }
    }
}

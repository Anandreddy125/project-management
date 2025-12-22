pipeline {
    agent any

    options {
        disableConcurrentBuilds()
        timestamps()
        timeout(time: 60, unit: 'MINUTES')
    }

    environment {
        GIT_REPO                   = "https://github.com/Anandreddy125/project-management.git"
        GIT_CREDENTIALS_ID         = "github-anand"
        DOCKER_CREDENTIALS_ID      = "docker-test"
        IMAGE_NAME                 = "anrs125/testing-repo"

    }

    stages {

        /* ---------------- CHECKOUT ---------------- */
        stage('Checkout Code') {
            steps {
                checkout scm

                script {
                    if (!env.TAG_NAME) {
                        echo "⏭️ Not a tag build. Skipping production deployment."
                        currentBuild.result = 'SUCCESS'
                        error("Pipeline stopped: branch push detected.")
                    }

                    env.IMAGE_TAG = env.TAG_NAME
                    echo "🚀 Production release detected: ${env.IMAGE_TAG}"
                }
            }
        }

        /* ---------------- DOCKER BUILD ---------------- */
        stage('Docker Build & Push') {
            steps {
                withCredentials([usernamePassword(
                    credentialsId: env.DOCKER_CREDENTIALS_ID,
                    usernameVariable: 'DOCKER_USER',
                    passwordVariable: 'DOCKER_PASSWORD'
                )]) {
                    sh """
                        echo \$DOCKER_PASSWORD | docker login -u \$DOCKER_USER --password-stdin
                        docker build -t ${IMAGE_NAME}:${IMAGE_TAG} .
                        docker push ${IMAGE_NAME}:${IMAGE_TAG}
                        docker logout
                    """
                }
            }
        }

        /* ---------------- DEPLOY ---------------- */
        stage('Deploy to Production') {
            steps {
                dir('kubernetes') {
                    withKubeConfig(credentialsId: env.KUBERNETES_CREDENTIALS_ID) {
                        sh """
                            sed -i 's|image: .*|image: ${IMAGE_NAME}:${IMAGE_TAG}|' ${DEPLOYMENT_FILE}
                            kubectl apply -f ${DEPLOYMENT_FILE} -n ${NAMESPACE}
                            kubectl rollout status deployment/${DEPLOYMENT_NAME} -n ${NAMESPACE}
                        """
                    }
                }
            }
        }
    }

    post {
        success {
            echo "✅ Production deployment successful for tag: ${IMAGE_TAG}"
        }
        failure {
            echo "❌ Deployment failed for tag: ${IMAGE_TAG}"
        }
        always {
            cleanWs()
        }
    }
}

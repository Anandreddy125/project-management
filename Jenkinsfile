pipeline {
    agent any

    options {
        disableConcurrentBuilds()
        timestamps()
        timeout(time: 60, unit: 'MINUTES')
    }

    environment {
        GIT_REPO                  = "https://github.com/Anandreddy125/project-management.git"
        GIT_CREDENTIALS_ID        = "terra-github"
        DOCKER_CREDENTIALS_ID     = "anand-dockerhub"

        IMAGE_NAME                = "anrs125/reports-testing"
        KUBERNETES_CREDENTIALS_ID = "testing-anand"
        DEPLOYMENT_FILE           = "prod-reports.yaml"
        DEPLOYMENT_NAME           = "prod-reports-api"
        NAMESPACE                 = "reports-production"
    }

    stages {

        /* ---------------- CHECKOUT ---------------- */
        stage('Checkout Code') {
            steps {
                checkout scm
            }
        }

        /* ---------------- VALIDATE TAG ---------------- */
        stage('Validate Tag From Master') {
            steps {
                script {

                    /* ----- Skip non-tag builds ----- */
                    if (!env.TAG_NAME) {
                        echo "⏭️ Not a tag build. Skipping production deployment."
                        currentBuild.result = 'SUCCESS'
                        error("Branch build detected")
                    }

                    echo "🔖 Tag detected: ${env.TAG_NAME}"

                    /* ----- Fetch all refs ----- */
                    sh 'git fetch --all --tags'

                    /* ----- Check tag belongs to master ----- */
                    def branches = sh(
                        script: "git branch -r --contains ${env.TAG_NAME}",
                        returnStdout: true
                    ).trim()

                    if (!branches.contains('origin/master')) {
                        echo "❌ Tag ${env.TAG_NAME} was NOT created from master branch"
                        echo "Found in branches:"
                        echo branches
                        currentBuild.result = 'SUCCESS'
                        error("Invalid tag source")
                    }

                    echo "✅ Tag ${env.TAG_NAME} verified from master"
                    env.IMAGE_TAG = env.TAG_NAME
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


//added jenkinsfile on github
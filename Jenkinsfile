pipeline {
    agent any

    options {
        disableConcurrentBuilds()
        timestamps()
        timeout(time: 60, unit: 'MINUTES')
    }

    environment {
        GIT_REPO                   = "https://github.com/Anandreddy125/project-management.git"
        GIT_CREDENTIALS_ID         = "terra-github"
        DOCKER_CREDENTIALS_ID      = "anand-dockerhub"

<<<<<<< HEAD
        DEPLOY_ENV                 = "production"
        IMAGE_NAME                 = "anrs125/reports-tesing"
        KUBERNETES_CREDENTIALS_ID  = "testing-anand"
        DEPLOYMENT_FILE            = "prod-reports.yaml"
        DEPLOYMENT_NAME            = "prod-reports-api"
        NAMESPACE                  = "reports-production"
=======
        DEPLOY_ENV            = "production"
        IMAGE_NAME                = "anrs125/reports-tesing"
        KUBERNETES_CREDENTIALS_ID = "testing-anand"
        DEPLOYMENT_FILE       = "prod-reports.yaml"
        DEPLOYMENT_NAME       = "prod-reports-api"
        NAMESPACE             = "reports-production"
>>>>>>> a9b677d (Update Jenkinsfile pipeline logic)
    }

    stages {

        stage('Detect Tag') {
            steps {
                script {
                    if (!env.GIT_BRANCH?.startsWith("refs/tags/")) {
<<<<<<< HEAD
                        error("❌ This pipeline runs ONLY for git tags")
=======
                        error("This pipeline runs only for git tags")
>>>>>>> a9b677d (Update Jenkinsfile pipeline logic)
                    }

                    env.IMAGE_TAG = env.GIT_BRANCH
                        .replace("refs/tags/", "")
                        .replaceAll("\\^\\{\\}", "")
                        .trim()

<<<<<<< HEAD
                    echo "✅ Production release tag detected: ${env.IMAGE_TAG}"
=======
                    echo "Production release tag: ${env.IMAGE_TAG}"
>>>>>>> a9b677d (Update Jenkinsfile pipeline logic)
                }
            }
        }

        stage('Checkout Tag') {
            steps {
<<<<<<< HEAD
                checkout([
                    $class: 'GitSCM',
=======
                checkout([$class: 'GitSCM',
>>>>>>> a9b677d (Update Jenkinsfile pipeline logic)
                    branches: [[name: "refs/tags/${env.IMAGE_TAG}"]],
                    userRemoteConfigs: [[
                        url: env.GIT_REPO,
                        credentialsId: env.GIT_CREDENTIALS_ID
                    ]]
                ])
            }
        }

        stage('Docker Build & Push') {
            steps {
                withCredentials([usernamePassword(
                    credentialsId: env.DOCKER_CREDENTIALS_ID,
                    usernameVariable: 'DOCKER_USER',
                    passwordVariable: 'DOCKER_PASSWORD'
                )]) {
                    sh """
                        echo $DOCKER_PASSWORD | docker login -u $DOCKER_USER --password-stdin
                        docker build -t ${IMAGE_NAME}:${IMAGE_TAG} .
                        docker push ${IMAGE_NAME}:${IMAGE_TAG}
                        docker logout
                    """
                }
            }
        }

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
<<<<<<< HEAD
            echo "✅ Production deployment successful for tag ${IMAGE_TAG}"
        }
        failure {
            echo "❌ Production deployment failed"
=======
            echo "✅ Production deployment successful for ${IMAGE_TAG}"
>>>>>>> a9b677d (Update Jenkinsfile pipeline logic)
        }
        always {
            cleanWs()
        }
    }
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

        DEPLOY_ENV                 = "production"
        IMAGE_NAME                 = "anrs125/reports-tesing"
        KUBERNETES_CREDENTIALS_ID  = "testing-anand"
        DEPLOYMENT_FILE            = "prod-reports.yaml"
        DEPLOYMENT_NAME            = "prod-reports-api"
        NAMESPACE                  = "reports-production"
    }

    triggers {
        githubPush()
    }

    stages {

        /* ---------------- CHECK BRANCH ---------------- */
        stage('Validate Branch') {
            steps {
                script {
                    if (env.BRANCH_NAME != "master") {
                        error("❌ Production deployment allowed only from master branch")
                    }
                    echo "✅ Master branch detected"
                }
            }
        }

        /* ---------------- CHECKOUT ---------------- */
        stage('Checkout Master') {
            steps {
                checkout([$class: 'GitSCM',
                    branches: [[name: '*/master']],
                    userRemoteConfigs: [[
                        url: env.GIT_REPO,
                        credentialsId: env.GIT_CREDENTIALS_ID
                    ]]
                ])

                script {
                    env.IMAGE_TAG = sh(
                        script: "git rev-parse --short HEAD",
                        returnStdout: true
                    ).trim()
                    echo "Docker Image Tag: ${env.IMAGE_TAG}"
                }
            }
        }

        /* ---------------- BUILD & PUSH ---------------- */
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
            echo "✅ Production deployment successful from MASTER | Tag: ${IMAGE_TAG}"
        }
        always {
            cleanWs()
        }
    }
}

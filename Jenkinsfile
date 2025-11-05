pipeline {
    agent any
    environment {
        SCANNER_HOME = tool('sonar-scanner')
        GIT_REPO = "https://github.com/devaslanphp/project-management.git"
        GIT_CREDENTIALS_ID = "terra-github"
        DOCKER_CREDENTIALS_ID = "anand-dockerhub"
    }

    triggers {
        githubPush()
    }

    stages {
        stage('Clean Workspace') {
            steps {
                cleanWs()
            }
        }

        stage('Checkout Code') {
            steps {
                checkout([$class: 'GitSCM',
                    branches: [[name: "*/${env.BRANCH_NAME}"]],
                    userRemoteConfigs: [[
                        url: "${GIT_REPO}",
                        credentialsId: "${GIT_CREDENTIALS_ID}"
                    ]]
                ])
            }
        }

        stage('Determine Environment') {
            steps {
                script {
                    if (env.BRANCH_NAME == "main") {
                        env.DEPLOY_ENV = "staging"
                        env.IMAGE_NAME = "anrs125/terraform"
                        env.KUBERNETES_CREDENTIALS_ID = "reports-staging"
                        env.DEPLOY_FILE = "kubernetes/deploy.yaml"
                        env.TAG_TYPE = "commit"
                        env.SONAR_PROJECT = "Staging-reports"
                    } else if (env.BRANCH_NAME == "master") {
                        env.DEPLOY_ENV = "production"
                        env.IMAGE_NAME = "anrs125/farhan-testing"
                        env.KUBERNETES_CREDENTIALS_ID = "testing-k3s"
                        env.DEPLOY_FILE = "kubernetes/service.yaml"
                        env.TAG_TYPE = "release"
                        env.SONAR_PROJECT = "Production-reports"
                    } else {
                        error("❌ Unsupported branch: ${env.BRANCH_NAME}")
                    }
                    echo "🌿 Deploy environment: ${DEPLOY_ENV}"
                }
            }
        }

        stage('Set Build Tag') {
            steps {
                script {
                    if (env.TAG_TYPE == "commit") {
                        def commitId = sh(script: "git rev-parse --short HEAD", returnStdout: true).trim()
                        env.IMAGE_TAG = "${DEPLOY_ENV}-${commitId}"
                    } else {
                        def tagName = sh(script: "git describe --tags --exact-match HEAD 2>/dev/null || true", returnStdout: true).trim()
                        if (!tagName) {
                            error("❌ No release tag found. Production builds require a Git tag.")
                        }
                        env.IMAGE_TAG = tagName
                    }
                    echo "🏷️ Docker tag: ${IMAGE_TAG}"
                }
            }
        }

        stage('Docker Build & Push') {
            steps {
                script {
                    withCredentials([usernamePassword(credentialsId: DOCKER_CREDENTIALS_ID, usernameVariable: 'DOCKER_USER', passwordVariable: 'DOCKER_PASSWORD')]) {
                        sh """
                            echo ${DOCKER_PASSWORD} | docker login -u ${DOCKER_USER} --password-stdin
                            docker build -t ${IMAGE_NAME}:${IMAGE_TAG} --no-cache .
                            docker push ${IMAGE_NAME}:${IMAGE_TAG}
                        """
                    }
                }
            }
        }

        stage('Deploy to Kubernetes') {
            steps {
                script {
                    echo "🚀 Deploying ${IMAGE_NAME}:${IMAGE_TAG} to ${DEPLOY_ENV} using ${DEPLOY_FILE}"
                    withKubeConfig(credentialsId: KUBERNETES_CREDENTIALS_ID) {
                        sh """
                            sed -i 's|image: .*|image: ${IMAGE_NAME}:${IMAGE_TAG}|g' ${DEPLOY_FILE}
                            kubectl apply -f ${DEPLOY_FILE}
                            kubectl rollout status deployment/reports-api -n reports
                        """
                    }
                }
            }
        }
    }

    post {
        success {
            echo "✅ Deployment completed successfully for ${DEPLOY_ENV}"
        }
        failure {
            echo "❌ Deployment failed for ${DEPLOY_ENV}"
        }
    }
}

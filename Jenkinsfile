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

    /*
      ⚠️ IMPORTANT
      - Keep webhook enabled in GitHub (Pushes)
      - DO NOT use githubPush() trigger
      - We filter TAGS manually
    */
    triggers {
    githubPush()   // Trigger when GitHub pushes occur (branch or tag)
 }

    stages {

        /* ---------------- VALIDATE TAG TRIGGER ---------------- */
        stage('Validate Tag Trigger') {
            steps {
                script {
                    echo "GIT_BRANCH detected: ${env.GIT_BRANCH}"

                    // Jenkins sets:
                    // refs/tags/v1.2.3  -> tag
                    // origin/master    -> branch
                    if (!env.GIT_BRANCH || !env.GIT_BRANCH.startsWith("refs/tags/")) {
                        currentBuild.result = 'ABORTED'
                        error("Build aborted: Not a tag push. Only tag pushes are allowed.")
                    }

                    // Extract tag name
                    env.GIT_TAG = env.GIT_BRANCH.replace("refs/tags/", "")
                    echo "✅ Tag detected: ${env.GIT_TAG}"
                }
            }
        }

        /* ---------------- CHECKOUT TAG ---------------- */
        stage('Checkout Code (Tag)') {
            steps {
                checkout([
                    $class: 'GitSCM',
                    branches: [[name: "refs/tags/${env.GIT_TAG}"]],
                    userRemoteConfigs: [[
                        url: env.GIT_REPO,
                        credentialsId: env.GIT_CREDENTIALS_ID
                    ]]
                ])
            }
        }

        /* ---------------- DETERMINE ENV ---------------- */
        stage('Determine Environment') {
            steps {
                script {
                    // Tags always deploy to production
                    env.DEPLOY_ENV = "production"
                    env.IMAGE_NAME = "prophazedocker/i-report"
                    env.IMAGE_TAG  = env.GIT_TAG

                    echo """
                    Deployment Info
                    --------------------
                    Environment : ${env.DEPLOY_ENV}
                    Image       : ${env.IMAGE_NAME}
                    Tag         : ${env.IMAGE_TAG}
                    """
                }
            }
        }

        /* ---------------- BUILD & PUSH (OPTIONAL) ---------------- */
        /*
        stage('Docker Login') {
            steps {
                withCredentials([usernamePassword(
                    credentialsId: env.DOCKER_CREDENTIALS_ID,
                    usernameVariable: 'DOCKER_USER',
                    passwordVariable: 'DOCKER_PASSWORD'
                )]) {
                    sh 'echo $DOCKER_PASSWORD | docker login -u $DOCKER_USER --password-stdin'
                }
            }
        }

        stage('Docker Build & Push') {
            steps {
                script {
                    def imageFull = "${env.IMAGE_NAME}:${env.IMAGE_TAG}"
                    sh """
                        docker build -t ${imageFull} .
                        docker push ${imageFull}
                        docker logout
                    """
                }
            }
        }
        */

        /* ---------------- DEPLOY (OPTIONAL) ---------------- */
        /*
        stage('Deploy to Kubernetes') {
            steps {
                echo "Deploying ${env.IMAGE_NAME}:${env.IMAGE_TAG} to production"
                // kubectl / ArgoCD deploy here
            }
        }
        */
    }

    post {
        success {
            script {
                slackSend(
                    channel: 'C09M08HUK8W',
                    color: '#36A64F',
                    tokenCredentialId: 'slack-token',
                    message: ":white_check_mark: *Production Deployment Successful*\n\n*Tag:* ${env.GIT_TAG}\n*Image:* ${env.IMAGE_NAME}:${env.IMAGE_TAG}\n<${env.BUILD_URL}|View Build>"
                )
            }
        }

        failure {
            script {
                slackSend(
                    channel: 'C09M08HUK8W',
                    color: '#FF0000',
                    tokenCredentialId: 'slack-token',
                    message: ":x: *Build Failed*\n<${env.BUILD_URL}|View Logs>"
                )
            }
        }

        aborted {
            echo "Build aborted (non-tag push)"
        }

        always {
            cleanWs()
        }
    }
}

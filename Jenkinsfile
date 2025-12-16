pipeline {
    agent any

    options {
        disableConcurrentBuilds()
        timestamps()
        timeout(time: 60, unit: 'MINUTES')
        skipDefaultCheckout(true)
    }

    environment {
     //   SCANNER_HOME          = tool('sonar-scanner')
        GIT_REPO              = "https://github.com/Anandreddy125/project-management.git"
        GIT_CREDENTIALS_ID    = "terra-github"
        DOCKER_CREDENTIALS_ID = "anand-dockerhub"
     //   SONARQUBE_ENV         = "sonar-server"
     //   NAMESPACE             = "reports"
    }
    parameters {
        choice(name: 'BRANCH_PARAM', choices: ['staging', 'master'], description: 'Select branch to build manually')
        booleanParam(name: 'ROLLBACK', defaultValue: false, description: 'Rollback to TARGET_VERSION instead of deploy')
        string(name: 'TARGET_VERSION', defaultValue: '', description: 'Target Docker tag for rollback (if enabled)')
    }
    triggers {
        githubPush()
    }
    stages {

        /* ---------------- CLEAN ---------------- */
        stage('Clean Workspace') {
            steps { cleanWs() }
        }

        /* ---------------- CHECKOUT (BRANCH / TAG) ---------------- */
        stage('Checkout Code') {
            steps {
                script {
                    def ref

                    if (env.GIT_TAG_NAME) {
                        ref = "refs/tags/${env.GIT_TAG_NAME}"
                        echo "🏷️ Tag build detected: ${env.GIT_TAG_NAME}"
                    } else {
                        ref = "*/${params.BRANCH_PARAM}"
                        echo "🌿 Branch build detected: ${params.BRANCH_PARAM}"
                    }

                    checkout([
                        $class: 'GitSCM',
                        branches: [[name: ref]],
                        userRemoteConfigs: [[
                            url: env.GIT_REPO,
                            credentialsId: env.GIT_CREDENTIALS_ID
                        ]]
                    ])
                }
            }
        }

        /* ---------------- ENV SELECTION ---------------- */
        stage('Determine Environment') {
            steps {
                script {

                    if (env.GIT_TAG_NAME) {
                        // 🔴 PRODUCTION (TAG ONLY)
                        env.DEPLOY_ENV = "production"
                        env.IMAGE_NAME = "anrs125/reports-tesing"
                        env.KUBERNETES_CREDENTIALS_ID = "k3s-report-staging"
                        env.DEPLOYMENT_FILE = "prod-reports.yaml"
                        env.DEPLOYMENT_NAME = "prod-reports-api"
                        env.TAG_TYPE = "release"

                    } else {
                        // 🟡 STAGING (BRANCH)
                        env.DEPLOY_ENV = "staging"
                        env.IMAGE_NAME = "anrs125/reports-tesing"
                        env.KUBERNETES_CREDENTIALS_ID = "reports-staging"
                        env.DEPLOYMENT_FILE = "staging-report.yaml"
                        env.DEPLOYMENT_NAME = "staging-reports-api"
                        env.TAG_TYPE = "commit"
                    }

                    echo """
                    🚀 Deployment Context
                    ----------------------
                    Git Tag     : ${env.GIT_TAG_NAME ?: 'N/A'}
                    Environment : ${env.DEPLOY_ENV}
                    Image Repo  : ${env.IMAGE_NAME}
                    """
                }
            }
        }

        /* ---------------- TAG GENERATION ---------------- */
        stage('Generate Docker Tag') {
            steps {
                script {
                    if (params.ROLLBACK) {
                        if (!params.TARGET_VERSION?.trim()) {
                            error("Rollback requested but TARGET_VERSION is empty")
                        }
                        env.IMAGE_TAG = params.TARGET_VERSION.trim()

                    } else if (env.TAG_TYPE == "commit") {
                        def commit = sh(script: "git rev-parse --short HEAD", returnStdout: true).trim()
                        env.IMAGE_TAG = "staging-${commit}"

                    } else {
                        if (!env.GIT_TAG_NAME) {
                            error("Tag not found. Production requires a git tag.")
                        }
                        env.IMAGE_TAG = env.GIT_TAG_NAME
                    }

                    echo "🐳 Docker Tag: ${env.IMAGE_TAG}"
                }
            }
        }

        /* ---------------- DOCKER BUILD ---------------- */
        stage('Docker Build & Push') {
            when { expression { !params.ROLLBACK } }
            steps {
                withCredentials([usernamePassword(
                    credentialsId: env.DOCKER_CREDENTIALS_ID,
                    usernameVariable: 'DOCKER_USER',
                    passwordVariable: 'DOCKER_PASS'
                )]) {
                    sh """
                        echo $DOCKER_PASS | docker login -u $DOCKER_USER --password-stdin
                        docker build -t ${env.IMAGE_NAME}:${env.IMAGE_TAG} .
                        docker push ${env.IMAGE_NAME}:${env.IMAGE_TAG}
                        docker logout
                    """
                }
            }
        }
    }

    /* ---------------- NOTIFICATIONS ---------------- */
    post {
        success {
            slackSend(
                channel: '#jenkins-alerts',
                color: '#36A64F',
                tokenCredentialId: 'slack-token',
                message: "✅ *Deployment Successful*\nEnv: ${env.DEPLOY_ENV}\nImage: ${env.IMAGE_NAME}:${env.IMAGE_TAG}\n${env.BUILD_URL}"
            )
        }
        failure {
            slackSend(
                channel: '#jenkins-alerts',
                color: '#FF0000',
                tokenCredentialId: 'slack-token',
                message: "❌ *Build Failed*\n${env.BUILD_URL}"
            )
        }
        always {
            cleanWs()
        }
    }
}
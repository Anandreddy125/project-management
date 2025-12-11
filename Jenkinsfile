pipeline {
    agent any

    options {
        disableConcurrentBuilds()
        timestamps()
        timeout(time: 60, unit: 'MINUTES')
    }

    environment {
        SCANNER_HOME          = tool('sonar-scanner')
        GIT_REPO              = "https://github.com/Anandreddy125/project-management.git"
        GIT_CREDENTIALS_ID    = "terra-github"
        DOCKER_CREDENTIALS_ID = "anand-dockerhub" 
        SONARQUBE_ENV         = "sonar-server"
        NAMESPACE             = "reports"
    }

    parameters {
        choice(name: 'BRANCH_PARAM', choices: ['staging', 'master'], description: 'Select branch for manual build')
        booleanParam(name: 'ROLLBACK', defaultValue: false, description: 'Rollback to TARGET_VERSION')
        string(name: 'TARGET_VERSION', defaultValue: '', description: 'Docker tag for rollback')
    }

    triggers {
        githubPush()
    }

    stages {

        stage('Clean Workspace') {
            steps { cleanWs() }
        }

        stage('Checkout Code') {
            steps {
                script {
                    def branchName = env.BRANCH_NAME ?: params.BRANCH_PARAM

                    echo "🔹 Checking out branch: ${branchName}"

                    checkout([
                        $class: 'GitSCM',
                        branches: [[name: "*/${branchName}"]],
                        userRemoteConfigs: [[
                            url: env.GIT_REPO,
                            credentialsId: env.GIT_CREDENTIALS_ID
                        ]]
                    ])

                    env.ACTUAL_BRANCH = branchName
                }
            }
        }

        stage('Determine Environment') {
            steps {
                script {
                    if (env.ACTUAL_BRANCH == "staging") {
                        env.DEPLOY_ENV = "staging"
                        env.IMAGE_NAME = "panrs125/sample-private"
                        env.KUBERNETES_CREDENTIALS_ID = "reports-staging1"
                        env.DEPLOYMENT_FILE = "staging-report.yaml"
                        env.DEPLOYMENT_NAME = "staging-reports-api"
                        env.TAG_TYPE = "commit"
                    } 
                    else if (env.ACTUAL_BRANCH == "master") {
                        env.DEPLOY_ENV = "production"
                        env.IMAGE_NAME = "panrs125/sample-private1"
                        env.KUBERNETES_CREDENTIALS_ID = "k3s-report-staging1"
                        env.DEPLOYMENT_FILE = "prod-reports.yaml"
                        env.DEPLOYMENT_NAME = "prod-reports-api"
                        env.TAG_TYPE = "release"
                    } 
                    else {
                        error("Unsupported branch: ${env.ACTUAL_BRANCH}")
                    }

                    echo """
                    ================================
                    📌 Environment Configuration
                    ----------------------------
                    Branch:           ${env.ACTUAL_BRANCH}
                    Deploy Env:       ${env.DEPLOY_ENV}
                    Docker Image:     ${env.IMAGE_NAME}
                    Tag Mode:         ${env.TAG_TYPE}
                    Namespace:        ${env.NAMESPACE}
                    Deployment File:  ${env.DEPLOYMENT_FILE}
                    ================================
                    """
                }
            }
        }

        stage('Trivy Filesystem Scan') {
            steps {
                sh "trivy fs . --severity HIGH,CRITICAL > trivyfs.txt || true"
            }
        }

        stage('Generate Docker Tag') {
            steps {
                script {
                    def commitId = sh(script: "git rev-parse --short HEAD", returnStdout: true).trim()
                    def imageTag

                    if (params.ROLLBACK) {
                        if (!params.TARGET_VERSION.trim()) {
                            error("Rollback requires TARGET_VERSION")
                        }
                        imageTag = params.TARGET_VERSION.trim()
                    }
                    else if (env.TAG_TYPE == "commit") {
                        imageTag = "staging-${commitId}"
                    }
                    else if (env.TAG_TYPE == "release") {

                        // Get Git tag — production MUST have a tag
                        def tagName = sh(
                            script: "git describe --tags --exact-match 2>/dev/null || true",
                            returnStdout: true
                        ).trim()

                        if (!tagName) {
                            error("""
                            ❌ Production build requires Git tag.
                            Example:
                                git tag v2.0.6
                                git push origin v2.0.6
                            """)
                        }

                        imageTag = tagName
                    }

                    env.IMAGE_TAG = imageTag
                    echo "📦 Final Docker Image Tag = ${env.IMAGE_TAG}"
                }
            }
        }

        stage('🔐 Docker Login') {
            steps {
                withCredentials([usernamePassword(credentialsId: env.DOCKER_CREDENTIALS_ID,
                    usernameVariable: 'DOCKER_USER', passwordVariable: 'DOCKER_PASSWORD')]) {
                    sh "echo ${DOCKER_PASSWORD} | docker login -u ${DOCKER_USER} --password-stdin"
                }
            }
        }

        stage('Docker Build & Push') {
            when { expression { return !params.ROLLBACK } }
            steps {
                script {
                    def imageFull = "${env.IMAGE_NAME}:${env.IMAGE_TAG}"

                    sh """
                        docker build --pull --no-cache -t ${imageFull} .
                        docker push ${imageFull}
                    """
                }
            }
        }
    }
}
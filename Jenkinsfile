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

        // Fill these when you want to re-enable K8s deploys
        // NAMESPACE             = "reports"
        // KUBERNETES_CREDENTIALS_ID = "reports-staging"
        // DEPLOYMENT_FILE       = "staging-report.yaml"
        // DEPLOYMENT_NAME       = "staging-reports-api"
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
        stage('Clean Workspace') {
            steps { cleanWs() }
        }

        stage('Checkout Code') {
            steps {
                script {
                    def branchName = env.BRANCH_NAME ?: params.BRANCH_PARAM
                    echo ":small_blue_diamond: Checking out branch: ${branchName}"
                    checkout([$class: 'GitSCM',
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
                        env.IMAGE_NAME = "anrs125/reports-tesing"
                        // env.KUBERNETES_CREDENTIALS_ID = "reports-staging"
                        // env.DEPLOYMENT_FILE = "staging-report.yaml"
                        // env.DEPLOYMENT_NAME = "staging-reports-api"
                        env.TAG_TYPE = "commit"
                    } else if (env.ACTUAL_BRANCH == "master") {
                        env.DEPLOY_ENV = "production"
                        env.IMAGE_NAME = "anrs125/reports-tesing"
                        // env.KUBERNETES_CREDENTIALS_ID = "k3s-report-staging"
                        // env.DEPLOYMENT_FILE = "prod-reports.yaml"
                        // env.DEPLOYMENT_NAME = "prod-reports-api"
                        env.TAG_TYPE = "release"
                    } else {
                        error("Unsupported branch: ${env.ACTUAL_BRANCH}")
                    }
                    echo """
                    Environment Info
                    ----------------------
                    Branch: ${env.ACTUAL_BRANCH}
                    Deploy: ${env.DEPLOY_ENV}
                    Repo:   ${env.IMAGE_NAME}
                    Mode:   ${env.TAG_TYPE}
                    Namespace: ${env.NAMESPACE}
                    Deployment File: ${env.DEPLOYMENT_FILE}
                    """
                }
            }
        }

        // Auto-commit & push when building staging (optional, no-op if no changes)
        stage('Auto Commit & Push (staging only)') {
            when {
                expression { env.ACTUAL_BRANCH == 'staging' && !params.ROLLBACK }
            }
            steps {
                script {
                    withCredentials([
                        gitUsernamePassword(credentialsId: env.GIT_CREDENTIALS_ID, gitToolName: 'Default')
                    ]) {
                        sh """
                            git config user.name "jenkins-ci"
                            git config user.email "jenkins-ci@prophaze.local"

                            # TODO: apply any automatic changes here if needed
                            # e.g. ./scripts/update-version.sh

                            git status
                            git add -A
                            if git diff --cached --quiet; then
                              echo "No changes to commit"
                            else
                              git commit -m "[CI] Auto changes from Jenkins"
                              git push origin ${env.ACTUAL_BRANCH}
                            fi
                        """
                    }
                }
            }
        }

        stage('Generate Docker Tag') {
            steps {
                script {
                    def commitId = sh(script: "git rev-parse HEAD | cut -c1-7", returnStdout: true).trim()
                    def imageTag = ""
                    if (params.ROLLBACK) {
                        if (!params.TARGET_VERSION?.trim()) {
                            error("Rollback requested but no TARGET_VERSION provided.")
                        }
                        imageTag = params.TARGET_VERSION.trim()
                    } else if (env.TAG_TYPE == "commit") {
                        imageTag = "staging-${commitId}"
                    } else if (env.TAG_TYPE == "release") {
                        def tagName = sh(
                            script: "git describe --tags --exact-match HEAD 2>/dev/null || true",
                            returnStdout: true
                        ).trim()
                        if (!tagName) {
                            error("Tag not found on HEAD. For production, create and push a Git tag first.")
                        }
                        imageTag = tagName
                    }
                    env.IMAGE_TAG = imageTag
                    echo ":rocket: FINAL Docker Tag: ${env.IMAGE_TAG}"
                }
            }
        }

        stage('Docker Login') {
            steps {
                script {
                    withCredentials([usernamePassword(credentialsId: env.DOCKER_CREDENTIALS_ID,
                        usernameVariable: 'DOCKER_USER', passwordVariable: 'DOCKER_PASSWORD')]) {
                        sh "echo ${DOCKER_PASSWORD} | docker login -u ${DOCKER_USER} --password-stdin"
                    }
                }
            }
        }

        stage('Docker Build & Push') {
            when { expression { return !params.ROLLBACK } }
            steps {
                script {
                    def imageFull = "${env.IMAGE_NAME}:${env.IMAGE_TAG}"
                    echo "Building Docker image: ${imageFull}"
                    sh """
                        docker build --pull --no-cache -t ${imageFull} .
                    """
                }
            }
        }
    }

    post {
        success {
            script {
                slackSend(
                    channel: 'C09M08HUK8W',
                    color: '#36A64F',
                    tokenCredentialId: 'slack-token',
                    message: ":white_check_mark: *Deployment Successful!*\n\n*Env:* ${env.DEPLOY_ENV}\n*Image:* ${env.IMAGE_NAME}:${env.IMAGE_TAG}\n<${env.BUILD_URL}|View Build>"
                )
                emailext(
                    attachLog: true,
                    subject: "Jenkins Pipeline Success - ${env.JOB_NAME}",
                    body: """
                        <b>Project:</b> ${env.JOB_NAME}<br/>
                        <b>Build Number:</b> ${env.BUILD_NUMBER}<br/>
                        <b>Status:</b> ${currentBuild.result}<br/>
                        <b>Docker Image:</b> ${env.IMAGE_NAME}:${env.IMAGE_TAG}<br/>
                        <b>Environment:</b> ${env.DEPLOY_ENV}<br/>
                        <b>Namespace:</b> ${env.NAMESPACE}<br/>
                        <b>Deployment File:</b> ${env.DEPLOYMENT_FILE}<br/>
                        <b>URL:</b> <a href="${env.BUILD_URL}">${env.BUILD_URL}</a><br/><br/>
                        Trivy & SonarQube reports attached.
                    """,
                    to: 'infra.alerts@prophaze.com',
                    attachmentsPattern: 'trivyfs.txt,trivyimage.txt'
                )
            }
        }
        failure {
            script {
                slackSend(
                    channel: '#C09M08HUK8W',
                    color: '#FF0000',
                    tokenCredentialId: 'slack-token',
                    message: ":x: *Build Failed!*\n\n*Env:* ${env.DEPLOY_ENV}\n<${env.BUILD_URL}|View Logs>"
                )
            }
        }
        always {
            echo 'Pipeline completed.'
            cleanWs()
        }
    }
}

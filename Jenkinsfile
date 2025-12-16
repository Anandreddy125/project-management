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

        // For future K8s deploys
        // NAMESPACE               = "reports"
        // KUBERNETES_CREDENTIALS_ID = "reports-staging"
        // DEPLOYMENT_FILE         = "staging-report.yaml"
        // DEPLOYMENT_NAME         = "staging-reports-api"
    }

    parameters {
        // For manual runs only; normal production runs come from tag builds
        choice(name: 'BRANCH_PARAM', choices: ['staging', 'master'], description: 'Select branch when running manually (non-tag builds)')
        booleanParam(name: 'ROLLBACK', defaultValue: false, description: 'Rollback to TARGET_VERSION instead of deploy')
        string(name: 'TARGET_VERSION', defaultValue: '', description: 'Target Docker tag for rollback (if enabled)')
    }

    // Still use GitHub webhook; job SCM must be configured with refs/tags/** for tag builds
    triggers {
        githubPush()
    }

    stages {
        stage('Checkout Code') {
            steps {
                script {
                    if (buildingTag()) {
                        echo ":small_blue_diamond: Detected TAG build: ${env.TAG_NAME}"
                        checkout([
                            $class: 'GitSCM',
                            branches: [[name: "refs/tags/${env.TAG_NAME}"]],
                            userRemoteConfigs: [[
                                url: env.GIT_REPO,
                                credentialsId: env.GIT_CREDENTIALS_ID
                            ]]
                        ])
                        env.ACTUAL_BRANCH = "master"    // logically a prod build
                    } else {
                        // manual / non-tag build (e.g. staging)
                        def branchName = env.BRANCH_NAME ?: params.BRANCH_PARAM
                        echo ":small_blue_diamond: Detected BRANCH build: ${branchName}"
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
        }

        stage('Determine Environment') {
            steps {
                script {
                    if (buildingTag()) {
                        // Production from tag
                        env.DEPLOY_ENV = "production"
                        env.IMAGE_NAME = "anrs125/reports-tesing"
                        env.TAG_TYPE   = "release"
                    } else if (env.ACTUAL_BRANCH == "staging") {
                        env.DEPLOY_ENV = "staging"
                        env.IMAGE_NAME = "anrs125/reports-tesing"
                        env.TAG_TYPE   = "commit"
                    } else if (env.ACTUAL_BRANCH == "master") {
                        env.DEPLOY_ENV = "production"
                        env.IMAGE_NAME = "anrs125/reports-tesing"
                        env.TAG_TYPE   = "release"
                    } else {
                        error("Unsupported ref: ACTUAL_BRANCH=${env.ACTUAL_BRANCH}, TAG_NAME=${env.TAG_NAME}")
                    }

                    echo """
                    Environment Info
                    ----------------------
                    Ref Type: ${buildingTag() ? "TAG" : "BRANCH"}
                    Branch:   ${env.ACTUAL_BRANCH}
                    Tag:      ${env.TAG_NAME}
                    Deploy:   ${env.DEPLOY_ENV}
                    Repo:     ${env.IMAGE_NAME}
                    Mode:     ${env.TAG_TYPE}
                    Namespace: ${env.NAMESPACE}
                    Deployment File: ${env.DEPLOYMENT_FILE}
                    """
                }
            }
        }

        // Optional: still only for staging if you want auto-commit there
        stage('Auto Commit & Push (staging only)') {
            when {
                expression { !buildingTag() && env.ACTUAL_BRANCH == 'staging' && !params.ROLLBACK }
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
                    def imageTag = ""

                    if (params.ROLLBACK) {
                        if (!params.TARGET_VERSION?.trim()) {
                            error("Rollback requested but no TARGET_VERSION provided.")
                        }
                        imageTag = params.TARGET_VERSION.trim()
                    } else if (buildingTag()) {
                        // Production/tag-based build → use tag name directly
                        if (!env.TAG_NAME) {
                            error("TAG build expected TAG_NAME, but TAG_NAME is empty.")
                        }
                        imageTag = env.TAG_NAME
                    } else if (env.TAG_TYPE == "commit") {
                        // Staging branch build
                        def commitId = sh(script: "git rev-parse HEAD | cut -c1-7", returnStdout: true).trim()
                        imageTag = "staging-${commitId}"
                    } else if (env.TAG_TYPE == "release") {
                        // Fallback for master non-tag build (should be rare)
                        def tagName = sh(
                            script: "git describe --tags --exact-match HEAD 2>/dev/null || true",
                            returnStdout: true
                        ).trim()
                        if (!tagName) {
                            error("Release build on master requires HEAD to have a tag.")
                        }
                        imageTag = tagName
                    } else {
                        error("Unknown TAG_TYPE: ${env.TAG_TYPE}")
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

        // Uncomment and configure when you want K8s deploys
        /*
        stage('Deploy to Kubernetes') {
            when { expression { return !params.ROLLBACK } }
            steps {
                script {
                    dir('deployments') {
                        withKubeConfig(credentialsId: env.KUBERNETES_CREDENTIALS_ID) {
                            echo "Deploying ${env.IMAGE_NAME}:${env.IMAGE_TAG} to ${env.DEPLOY_ENV} ..."
                            sh """
                                sed -i 's|image: ${env.IMAGE_NAME}:.*|image: ${env.IMAGE_NAME}:${env.IMAGE_TAG}|' ${env.DEPLOYMENT_FILE}
                                kubectl apply -f ${env.DEPLOYMENT_FILE} -n ${env.NAMESPACE}
                                kubectl rollout status deployment/${env.DEPLOYMENT_NAME} -n ${env.NAMESPACE} || {
                                    echo ":warning: Deployment failed, rolling back..."
                                    kubectl rollout undo deployment/${env.DEPLOYMENT_NAME} -n ${env.NAMESPACE}
                                    exit 1
                                }
                            """
                        }
                    }
                }
            }
        }

        stage('Rollback Version') {
            when { expression { return params.ROLLBACK && params.TARGET_VERSION?.trim() } }
            steps {
                script {
                    def rollbackVersion = params.TARGET_VERSION.trim()
                    echo "Rolling back to version: ${rollbackVersion}"
                    dir('deployments') {
                        withKubeConfig(credentialsId: env.KUBERNETES_CREDENTIALS_ID) {
                            sh """
                                sed -i 's|image: ${env.IMAGE_NAME}:.*|image: ${env.IMAGE_NAME}:${rollbackVersion}|' ${env.DEPLOYMENT_FILE}
                                kubectl apply -f ${env.DEPLOYMENT_FILE} -n ${env.NAMESPACE}
                                kubectl rollout status deployment/${env.DEPLOYMENT_NAME} -n ${env.NAMESPACE}
                            """
                        }
                    }
                }
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
                    """,
                    to: 'infra.alerts@prophaze.com'
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

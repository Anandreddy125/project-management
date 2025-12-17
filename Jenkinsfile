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

    parameters {
        choice(name: 'BRANCH_PARAM', choices: ['staging'], description: 'Manual build ONLY for staging')
        booleanParam(name: 'ROLLBACK', defaultValue: false, description: 'Rollback using TARGET_VERSION')
        string(name: 'TARGET_VERSION', defaultValue: '', description: 'Docker tag for rollback')
    }

    triggers {
        githubPush()
    }

    stages {

        /* ---------------- CLEAN ---------------- */
        stage('Clean Workspace') {
            steps { cleanWs() }
        }

        /* ---------------- CHECKOUT ---------------- */
        stage('Checkout Code') {
            steps {
                script {

                    checkout([
                        $class: 'GitSCM',
                        branches: [[name: '**']],
                        userRemoteConfigs: [[
                            url: env.GIT_REPO,
                            credentialsId: env.GIT_CREDENTIALS_ID
                        ]]
                    ])

                    // Detect tag or branch
                    env.GIT_TAG = sh(
                        script: "git describe --tags --exact-match HEAD 2>/dev/null || true",
                        returnStdout: true
                    ).trim()

                    env.GIT_BRANCH_NAME = sh(
                        script: "git branch -r --contains HEAD | grep origin/master || true",
                        returnStdout: true
                    ).trim()

                    echo "Detected TAG    : ${env.GIT_TAG}"
                    echo "Master contains : ${env.GIT_BRANCH_NAME}"
                }
            }
        }

        /* ---------------- TRIGGER CONTROL ---------------- */
        stage('Validate Trigger') {
            steps {
                script {

                    // ---- PRODUCTION ----
                    if (env.GIT_TAG) {

                        if (!env.GIT_BRANCH_NAME) {
                            error("❌ Tag is NOT on master branch. Aborting.")
                        }

                        env.DEPLOY_ENV = "production"
                        env.IMAGE_TAG  = env.GIT_TAG
                        env.IMAGE_NAME = "anrs125/reports-tesing"
                        env.DEPLOYMENT_FILE = "prod-reports.yaml"
                        env.DEPLOYMENT_NAME = "prod-reports-api"

                        echo "✅ Production release detected: ${env.IMAGE_TAG}"
                        return
                    }

                    // ---- STAGING ----
                    if (!env.GIT_TAG && params.BRANCH_PARAM == "staging") {

                        env.DEPLOY_ENV = "staging"
                        env.IMAGE_NAME = "anrs125/reports-tesing"
                        env.DEPLOYMENT_FILE = "staging-report.yaml"
                        env.DEPLOYMENT_NAME = "staging-reports-api"

                        def commitId = sh(
                            script: "git rev-parse --short HEAD",
                            returnStdout: true
                        ).trim()

                        env.IMAGE_TAG = "staging-${commitId}"
                        echo "✅ Staging build detected"
                        return
                    }

                    // ---- BLOCK EVERYTHING ELSE ----
                    error("❌ Not a valid trigger (No tag on master / Not staging push)")
                }
            }
        }

        /* ---------------- DOCKER LOGIN ---------------- */
        stage('Docker Login') {
            when { expression { !params.ROLLBACK } }
            steps {
                withCredentials([
                    usernamePassword(
                        credentialsId: env.DOCKER_CREDENTIALS_ID,
                        usernameVariable: 'DOCKER_USER',
                        passwordVariable: 'DOCKER_PASSWORD'
                    )
                ]) {
                    sh "echo ${DOCKER_PASSWORD} | docker login -u ${DOCKER_USER} --password-stdin"
                }
            }
        }

        /* ---------------- BUILD & PUSH ---------------- */
        stage('Docker Build & Push') {
            when { expression { !params.ROLLBACK } }
            steps {
                script {
                    def imageFull = "${env.IMAGE_NAME}:${env.IMAGE_TAG}"
                    sh """
                      docker build --no-cache -t ${imageFull} .
                      docker push ${imageFull}
                      docker logout
                    """
                }
            }
        }
    }
}

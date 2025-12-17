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
        choice(
            name: 'BRANCH_PARAM',
            choices: ['staging', 'master'],
            description: 'Manual build branch (tags auto-detected)'
        )
        booleanParam(
            name: 'ROLLBACK',
            defaultValue: false,
            description: 'Rollback to TARGET_VERSION'
        )
        string(
            name: 'TARGET_VERSION',
            defaultValue: '',
            description: 'Docker tag for rollback'
        )
    }

    triggers {
        githubPush()                 // staging
        pollSCM('H/5 * * * *')        // production tags
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
                    sh "git init"
                    sh "git remote add origin ${env.GIT_REPO}"

                    sh """
                        git fetch --all --tags
                    """

                    def isTag = sh(
                        script: "git describe --tags --exact-match HEAD >/dev/null 2>&1",
                        returnStatus: true
                    ) == 0

                    if (isTag) {
                        env.BUILD_TYPE = "tag"
                        env.GIT_REF = sh(
                            script: "git describe --tags --exact-match HEAD",
                            returnStdout: true
                        ).trim()
                    } else {
                        env.BUILD_TYPE = "branch"
                        env.GIT_REF = params.BRANCH_PARAM
                    }

                    echo "Build Type: ${env.BUILD_TYPE}"
                    echo "Git Ref: ${env.GIT_REF}"

                    checkout([
                        $class: 'GitSCM',
                        branches: [[name: env.BUILD_TYPE == "tag" ? "refs/tags/${env.GIT_REF}" : "*/${env.GIT_REF}"]],
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
                    if (env.BUILD_TYPE == "branch" && env.GIT_REF == "staging") {
                        env.DEPLOY_ENV = "staging"
                        env.IMAGE_NAME = "anrs125/reports-tesing"
                        env.DEPLOYMENT_FILE = "staging-report.yaml"
                        env.TAG_TYPE = "commit"
                    }
                    else if (env.BUILD_TYPE == "tag") {
                        env.DEPLOY_ENV = "production"
                        env.IMAGE_NAME = "anrs125/reports-tesing"
                        env.DEPLOYMENT_FILE = "prod-reports.yaml"
                        env.TAG_TYPE = "release"
                    }
                    else {
                        error("Unsupported trigger")
                    }

                    echo """
                    =====================
                    DEPLOYMENT INFO
                    =====================
                    Environment: ${env.DEPLOY_ENV}
                    Image: ${env.IMAGE_NAME}
                    Mode: ${env.TAG_TYPE}
                    """
                }
            }
        }

        /* ---------------- TAG GENERATION ---------------- */
        stage('Generate Docker Tag') {
            steps {
                script {
                    if (params.ROLLBACK) {
                        env.IMAGE_TAG = params.TARGET_VERSION
                    }
                    else if (env.TAG_TYPE == "commit") {
                        env.IMAGE_TAG = "staging-${sh(script: 'git rev-parse --short HEAD', returnStdout: true).trim()}"
                    }
                    else {
                        env.IMAGE_TAG = env.GIT_REF
                    }

                    echo "Docker Tag: ${env.IMAGE_TAG}"
                }
            }
        }

        /* ---------------- DOCKER BUILD ---------------- */
        stage('Docker Build & Push') {
            when { expression { !params.ROLLBACK } }
            steps {
                script {
                    withCredentials([usernamePassword(
                        credentialsId: env.DOCKER_CREDENTIALS_ID,
                        usernameVariable: 'DOCKER_USER',
                        passwordVariable: 'DOCKER_PASSWORD'
                    )]) {
                        sh """
                            echo $DOCKER_PASSWORD | docker login -u $DOCKER_USER --password-stdin
                            docker build -t ${env.IMAGE_NAME}:${env.IMAGE_TAG} .
                            docker push ${env.IMAGE_NAME}:${env.IMAGE_TAG}
                            docker logout
                        """
                    }
                }
            }
        }
    }
}
